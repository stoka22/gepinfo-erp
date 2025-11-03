<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\Card;
use Filament\Tables;
use App\Models\Company;
use Filament\Forms\Get;
use App\Models\Employee;
use App\Models\Position;
use App\Enums\TimeEntryType;
use App\Models\ShiftPattern;
use App\Services\CardService;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
//use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use App\Filament\Resources\EmployeeResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Filament\Resources\EmployeeResource\RelationManagers\SkillsRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\VacationAllowancesRelationManager;
use Filament\Tables\Actions\Action;
use Livewire\Component;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationGroup  = 'Dolgozók';
    protected static ?string $navigationLabel  = 'Dolgozók';
    protected static ?string $pluralModelLabel = 'Dolgozók';
    protected static ?string $modelLabel       = 'Dolgozó';

    protected static function currentUser(): ?\App\Models\User
    {
        return Filament::auth()->user() ?? Auth::user();
    }

    protected static function currentGroupId(): ?int
    {
        $u = static::currentUser();
        return $u?->company?->company_group_id ?? null;
    }

    /**
     * VISSZAADJA AZ ADOTT CÉGCSOPORTHOZ TARTOZÓ CÉGEK ID-IT.
     * FIX: a 'group_id' oszlop nem létezik; kizárólag 'company_group_id' alapján szűrünk.
     */
    protected static function groupCompanyIds(?int $groupId = null): array
    {
        $gid = $groupId ?? static::currentGroupId();
        if (!$gid) {
            $co = static::currentUser()?->company;
            return $co?->id ? [$co->id] : [];
        }
        return Company::query()
            ->where('company_group_id', $gid)
            ->pluck('id')
            ->all();
    }

    protected static function resolveGroupIdFromContext(?int $companyIdFromForm = null): ?int
    {
        if ($companyIdFromForm) {
            return (int) Company::whereKey($companyIdFromForm)->value('company_group_id');
        }
        return static::currentGroupId();
    }

    public static function getRelations(): array
    {
        $rels = [];

        if (Schema::hasTable('skills') && Schema::hasTable('employee_skill')) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\SkillsRelationManager::class;
        }

        if (Schema::hasTable('time_entries')) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager::class;
        }

        if (Schema::hasTable('vacation_allowances')) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\VacationAllowancesRelationManager::class;
        }

        // Kártyák csak akkor, ha a manager létezik és a tábla is megvan
        if (
            class_exists(\App\Filament\Resources\EmployeeResource\RelationManagers\CardsRelationManager::class)
            && Schema::hasTable('employee_cards')
        ) {
            $rels[] = \App\Filament\Resources\EmployeeResource\RelationManagers\CardsRelationManager::class;
        }

        return $rels; // <— NINCS beágyazás
    }


    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            //->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['company', 'companies']);

        $user = static::currentUser();
        if (! $user) {
            return $query->whereRaw('1=0');
        }
        if (($user->role ?? null) === 'admin') {
            return $query;
        }

        $companyIds = static::groupCompanyIds();
        if ($companyIds) {
            return $query->where(function (Builder $w) use ($companyIds) {
                $w->whereIn('company_id', $companyIds)
                  ->orWhereHas('companies', fn (Builder $c) => $c->whereIn('company_id', $companyIds));
            });
        }

        return $query->where('company_id', $user->company_id);
    }

    protected static function normalizeDate(null|string|\DateTimeInterface $value, string $out = 'Y-m-d'): ?string
    {
        if (!$value) return null;
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format($out);
        }
        $s = trim((string)$value);
        $formats = [
            'Y-m-d', 'Y.m.d', 'Y. m. d.', 'Y/m/d',
            'd.m.Y', 'd-m-Y', 'd/m/Y',
            'Y. F d.', 'Y. M d.',
        ];
        foreach ($formats as $f) {
            try { return Carbon::createFromFormat($f, $s)->format($out); } catch (\Throwable) {}
        }
        try { return Carbon::parse($s)->format($out); } catch (\Throwable) { return null; }
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        $isAdmin   = (Filament::auth()->user()?->role ?? null) === 'admin';
        $companyId = Filament::auth()->user()?->company_id;

        $ownerField = $isAdmin
            ? Forms\Components\Select::make('account_user_id')
                ->label('Bejelentkező felhasználó')
                ->relationship(
                    name: 'accountUser',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) use ($companyId) {
                        $query->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                              ->orderBy('name');
                    }
                )
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('— nincs hozzárendelve —')
            : Forms\Components\Hidden::make('created_by_user_id')
                ->default(fn () => Filament::auth()->id())
                ->dehydrated();

        return $form->schema([
            Forms\Components\Section::make('Alap adatok')->schema([
                $ownerField,
                Forms\Components\TextInput::make('name')->label('Név')->required(),

                Forms\Components\DatePicker::make('birth_date')
                    ->label('Születési dátum')
                    ->native(false)
                    ->displayFormat('Y. m. d.')
                    ->format('Y-m-d')
                    ->closeOnDateSelection(true)
                    ->weekStartsOnMonday()
                    ->extraAttributes([
                        'data-allow-input' => true,
                        'placeholder'      => 'éééé. hh. nn.',
                        'autocomplete'     => 'off',
                        'inputmode'        => 'numeric',
                    ])
                    ->afterStateHydrated(function ($component, $state) {
                        $component->state(static::normalizeDate($state, 'Y-m-d'));
                    })
                    ->dehydrateStateUsing(fn ($state) => static::normalizeDate($state, 'Y-m-d'))
                    ->rule('date'),

                Forms\Components\Select::make('position_id')
                    ->label('Pozíció')
                    ->native(false)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->getSearchResultsUsing(function (string $search): array {
                        $cid = Filament::auth()->user()?->company_id;
                        return Position::query()
                            ->when($cid, fn ($q) => $q->where('company_id', $cid))
                            ->where('active', true)
                            ->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                  ->orWhere('code', 'like', "%{$search}%");
                            })
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->getOptionLabelUsing(fn ($value) => Position::find($value)?->name)
                    ->relationship(
                        name: 'position',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            $cid = Filament::auth()->user()?->company_id;
                            $q->when($cid, fn ($qq) => $qq->where('company_id', $cid))
                              ->where('active', true)
                              ->orderBy('name');
                        }
                    )
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(100),
                        Forms\Components\TextInput::make('code')->label('Kód')->maxLength(50),
                        Forms\Components\Toggle::make('active')->label('Aktív')->default(true),
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn () => Filament::auth()->user()?->company_id)
                            ->dehydrated(true),
                    ])
                    ->createOptionAction(fn (Forms\Components\Actions\Action $action) => $action->label('Új pozíció létrehozása')),

                Forms\Components\TextInput::make('email')->email(),
                
                Forms\Components\Select::make('employment_type')
                    ->label('Foglalkoztatás')
                    ->options([
                        'full_time' => 'Teljes',
                        'part_time' => 'Részmunkaidő',
                        'casual'    => 'Alkalmi',
                    ])->required()->default('full_time'),
                
                Forms\Components\Select::make('shift_pattern_id')
                    ->label('Műszak')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->visible(fn () => Schema::hasTable('shift_patterns') && Schema::hasColumn('employees', 'shift_pattern_id'))
                    ->relationship(
                        name: 'shiftPattern',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($q) => $q->orderBy('name')
                    )
                    ->getOptionLabelUsing(function ($value) {
                        $p = ShiftPattern::find($value);
                        return $p ? "{$p->name} ({$p->start_time}–{$p->end_time}, {$p->days_label})" : null;
                    })
                    ->options(function () {
                        return ShiftPattern::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($p) => [$p->id => "{$p->name} ({$p->start_time}–{$p->end_time}, {$p->days_label})"])
                            ->toArray();
                    })
                    ->placeholder('Válaszd ki a dolgozó műszakmintáját')
                    ->columnSpan(2),
                                Forms\Components\DatePicker::make('hired_at')
                    ->label('Felvétel dátuma')
                    ->native(false)
                    ->displayFormat('Y. m. d.')
                    ->format('Y-m-d')
                    ->default(fn () => now()->toDateString())   // alapértelmezett: ma
                    ->closeOnDateSelection(true)
                    ->weekStartsOnMonday()
                    ->extraAttributes([
                        'data-allow-input' => true,
                        'placeholder'      => 'éééé. hh. nn.',
                        'autocomplete'     => 'off',
                        'inputmode'        => 'numeric',
                    ])
                    ->afterStateHydrated(function ($component, $state) {
                        // egységesítés a többi dátumhoz
                        $component->state(static::normalizeDate($state, 'Y-m-d'));
                    })
                    ->dehydrateStateUsing(fn ($state) => static::normalizeDate($state, 'Y-m-d'))
                    ->rule('date'),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\TextInput::make('rfid')
                    ->label('Kártyszám'),
                
                Forms\Components\Select::make('company_id')
                    ->label('Munkáltató (primer)')
                    ->visible(fn () => Schema::hasColumn('employees', 'company_id'))
                    ->relationship(name: 'company', titleAttribute: 'name')
                    ->options(function (Get $get) {
                        $gid = self::resolveGroupIdFromContext((int) $get('company_id'));
                        return Company::query()
                            ->when($gid, fn ($q) => $q->where('company_group_id', $gid))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->getOptionLabelUsing(fn ($value) => Company::find($value)?->name)
                    ->afterStateHydrated(function (callable $set, ?Employee $record) {
                        $set('company_id', $record?->company_id);
                    })
                    ->default(fn () => Filament::auth()->user()?->company_id)
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->placeholder('— Válassz munkáltatót —')
                    ->columnSpan(2),

                Forms\Components\Select::make('companies')
                    ->label('Mely cégeknél dolgozhat (cégcsoporton belül)')
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->relationship(name: 'companies', titleAttribute: 'name')
                    ->options(function (Get $get) {
                        $gid = self::resolveGroupIdFromContext((int) $get('company_id'));
                        return Company::query()
                            ->when($gid, fn ($q) => $q->where('company_group_id', $gid))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->columnSpan(2),
            ])->columns(4),

            Forms\Components\Section::make('Munkafolyamatok')->schema([
                Forms\Components\Select::make('workflows')
                    ->label('Workflows')
                    ->multiple()
                    ->native(false)
                    ->relationship(
                        name: 'workflows',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            if (
                                ($cid = static::currentUser()?->company_id) &&
                                Schema::hasColumn('workflows', 'company_id')
                            ) {
                                $q->where('workflows.company_id', $cid);
                            }
                            $q->orderBy('name');
                        }
                    )
                    ->preload()
                    ->searchable()
                    ->hint('Mely workflow-kban vehet részt'),
            ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        $groupId = static::currentGroupId();
        $groupCompanies = Company::query()
            ->when($groupId, fn($q) => $q->where('company_group_id', $groupId))
            ->orderBy('name')->pluck('name','id')->all();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Munkáltató (primer)')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('position.name')->label('Pozíció')->sortable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()->toggleable(),

                Tables\Columns\TextColumn::make('shiftPattern.name')
                    ->label('Műszak minta')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),

           /*     Tables\Columns\TextColumn::make('shiftPatternInfo')
                    ->label('Idő / Napok')
                    ->getStateUsing(fn (Employee $record) =>
                        $record->shiftPattern
                            ? "{$record->shiftPattern->start_time}–{$record->shiftPattern->end_time} • {$record->shiftPattern->days_label}"
                            : '—'
                    )
                    ->toggleable(),*/
                Tables\Columns\TextColumn::make('card.uid')
                    ->label('Kártya UID')
                    ->placeholder('— nincs —'),
            ])
            ->filters([
                TrashedFilter::make(),
                   
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Munkáltató (primer)')
                    ->options($groupCompanies),

                Tables\Filters\SelectFilter::make('member_company')
                    ->label('Cég (tagság)')
                    ->options($groupCompanies)
                    ->query(function (Builder $q, array $data) {
                        if (!empty($data['value'])) {
                            $companyId = (int) $data['value'];
                            $q->whereHas('companies', fn ($c) => $c->where('company_id', $companyId));
                        }
                    }),

                Tables\Filters\SelectFilter::make('employment_type')
                    ->label('Foglalkoztatás')
                    ->options([
                        'full_time' => 'Teljes munkaidő',
                        'part_time' => 'Részmunkaidő',
                        'casual'    => 'Alkalmi',
                    ]),

                TernaryFilter::make('present')
                    ->label('Jelenlét')
                    ->placeholder('Mind')
                    ->trueLabel('Bejelentkezve')
                    ->falseLabel('Nincs bejelentkezve')
                    ->queries(
                        true: fn (Builder $q) => $q->whereExists(function ($s) {
                            $s->select(DB::raw(1))
                              ->from('time_entries as te')
                              ->whereColumn('te.employee_id', 'employees.id')
                              ->whereNull('te.end_time')
                              ->whereNull('te.end_date');
                        }),
                        false: fn (Builder $q) => $q->whereNotExists(function ($s) {
                            $s->select(DB::raw(1))
                              ->from('time_entries as te')
                              ->whereColumn('te.employee_id', 'employees.id')
                              ->whereNull('te.end_time')
                              ->whereNull('te.end_date');
                        }),
                    ),
            ])
            ->headerActions([
                Tables\Actions\Action::make('group_company_quick')
                    ->label('Cég szűrés')
                    ->icon('heroicon-o-building-office-2')
                    ->form([
                        Forms\Components\Select::make('company')
                            ->label('Cég')
                            ->options($groupCompanies)
                            ->native(false)
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        $company = $data['company'] ?? null;
                        if ($company) {
                            return redirect()->to(url()->current().'?tableFilters[company_id][value]='.$company);
                        }
                    }),

                Tables\Actions\Action::make('export_pdf')
                    ->label('Export PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('company')
                            ->label('Cég (opcionális)')
                            ->options($groupCompanies)
                            ->native(false)
                            ->searchable(),
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Oszlopok')
                            ->options([
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position' => 'Pozíció',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                            ])
                            ->default(['name','company','position'])
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        $cols = $data['columns'] ?? ['name','company','position'];
                        $company = $data['company'] ?? null;

                        $q = static::getEloquentQuery()->clone();
                        if ($company) {
                            $q->where('company_id', $company);
                        }
                        $rows = $q->orderBy('name')->get();

                        $headers = [];
                        foreach ($cols as $c) {
                            $headers[] = match($c){
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position' => 'Pozíció',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                                default => ucfirst($c),
                            };
                        }

                        $html = '<html><head><meta charset="UTF-8"><style>
                            table{width:100%;border-collapse:collapse;font-size:12px}
                            th,td{border:1px solid #ccc;padding:6px;text-align:left}
                            h1{font-size:16px;margin:0 0 10px 0}
                        </style></head><body>';
                        $html .= '<h1>Dolgozók export (PDF)</h1>';
                        $html .= '<table><thead><tr>';
                        foreach ($headers as $h) { $html .= '<th>'.htmlspecialchars($h).'</th>'; }
                        $html .= '</tr></thead><tbody>';

                        foreach ($rows as $r) {
                            $html .= '<tr>';
                            foreach ($cols as $c) {
                                $val = match($c){
                                    'name' => $r->name,
                                    'company' => $r->company?->name,
                                    'position' => $r->position?->name,
                                    'phone' => $r->phone,
                                    'shift' => $r->shiftPattern?->name,
                                    default => '',
                                };
                                $html .= '<td>'.htmlspecialchars((string)$val).'</td>';
                            }
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table></body></html>';

                        $resp = new StreamedResponse(function () use ($html) {
                            $options = new \Dompdf\Options([
                                'isRemoteEnabled' => true,
                                'defaultFont' => 'DejaVu Sans',
                            ]);
                            $dompdf = new \Dompdf\Dompdf($options);
                            $dompdf->loadHtml($html, 'UTF-8');
                            $dompdf->setPaper('A4','portrait');
                            $dompdf->render();
                            echo $dompdf->output();
                        }, 200, [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'attachment; filename=\"employees.pdf\"',
                        ]);
                        return $resp;
                    }),

                Tables\Actions\Action::make('export_xls')
                    ->label('Export XLS')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->form([
                        Forms\Components\Select::make('company')
                            ->label('Cég (opcionális)')
                            ->options($groupCompanies)
                            ->native(false)
                            ->searchable(),
                        Forms\Components\CheckboxList::make('columns')
                            ->label('Oszlopok')
                            ->options([
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position' => 'Pozíció',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                            ])
                            ->default(['name','company','position','phone'] )
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        $cols = $data['columns'] ?? ['name','company','position','phone'];
                        $company = $data['company'] ?? null;

                        $q = static::getEloquentQuery()->clone();
                        if ($company) {
                            $q->where('company_id', $company);
                        }
                        $rows = $q->orderBy('name')->get();

                        $headers = [];
                        foreach ($cols as $c) {
                            $headers[] = match($c){
                                'name' => 'Név',
                                'company' => 'Cég',
                                'position' => 'Pozíció',
                                'phone' => 'Telefon',
                                'shift' => 'Műszak minta',
                                default => ucfirst($c),
                            };
                        }

                        $html = '<table border=\"1\"><thead><tr>';
                        foreach ($headers as $h) { $html .= '<th>'.htmlspecialchars($h).'</th>'; }
                        $html .= '</tr></thead><tbody>';
                        foreach ($rows as $r) {
                            $html .= '<tr>';
                            foreach ($cols as $c) {
                                $val = match($c){
                                    'name' => $r->name,
                                    'company' => $r->company?->name,
                                    'position' => $r->position?->name,
                                    'phone' => $r->phone,
                                    'shift' => $r->shiftPattern?->name,
                                    default => '',
                                };
                                $html .= '<td>'.htmlspecialchars((string)$val).'</td>';
                            }
                            $html .= '</tr>';
                        }
                        $html .= '</tbody></table>';

                        return new Response($html, 200, [
                            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                            'Content-Disposition' => 'attachment; filename=\"employees.xls\"',
                        ]);
                    }),
            ])
            ->actionsPosition(ActionsPosition::AfterColumns)
            ->actions([
                Tables\Actions\Action::make('assignCard')
                    ->label('')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn ($record) => ! $record->card) // 1-1 kapcsolat esetén
                    ->color('success')
                    ->tooltip('Kártya hozzárendelése')
                    ->form([
                        Forms\Components\Select::make('card_id')
                            ->label('Szabad kártya')
                            ->options(fn () => Card::available()->orderBy('uid')->pluck('uid', 'id'))
                            ->searchable()
                            ->required()
                            ->options(function () {
                                return Card::query()
                                    ->whereNull('employee_id')
                                    ->where('status', 'available')
                                    ->orderBy('uid')
                                    ->limit(100) // opcionális
                                    ->get()
                                    ->mapWithKeys(function (Card $c) {
                                        $label = trim($c->uid . ($c->notes ? ' - ' . $c->notes : ''));
                                        return [$c->id => $label];
                                    })
                                    ->toArray();
                            })
                             ->getSearchResultsUsing(function (string $search) {
                                return Card::query()
                                    ->whereNull('employee_id')
                                    ->where('status', 'available')
                                    ->where(function ($q) use ($search) {
                                        $q->where('uid', 'like', "%{$search}%")
                                        ->orWhere('notes', 'like', "%{$search}%");
                                    })
                                    ->orderBy('uid')
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Card $c) {
                                        $label = trim($c->uid . ($c->notes ? ' - ' . $c->notes : ''));
                                        return [$c->id => $label];
                                    })
                                    ->toArray();
                            })
                            // Ha már kiválasztott értéket kell visszaírni címkének
                            ->getOptionLabelUsing(function ($value) {
                                if (! $value) return null;
                                $c = Card::find($value);
                                return $c ? trim($c->uid . ($c->notes ? ' - ' . $c->notes : '')) : null;
                            }),
                    ])
                    ->action(function ($record, array $data) {
                        $card = \App\Models\Card::findOrFail($data['card_id']);
                        app(CardService::class)->assignByUid($record->id, $card->uid);
                        Notification::make()->title('Kártya hozzárendelve.')->success()->send();
                    })
                    ->after(function (Action $action, $livewire) {
                        $livewire->dispatch('refresh');   // táblát/oldalt újrarendereli
                    }),

                Tables\Actions\Action::make('unassignCard')
                    ->label('')
                    ->icon('heroicon-o-minus-circle')
                    ->visible(fn ($record) => (bool) $record->card)
                    ->color('warning')
                    ->tooltip('Kártya léválasztása')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(CardService::class)->unassign($record->card->id);
                        Notification::make()->title('Kártya leválasztva.')->success()->send();
                    })
                    ->after(function (Action $action, $livewire) {
                        $livewire->dispatch('refresh');   // táblát/oldalt újrarendereli
                    }),

               // Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('checkIn')
                    ->label('')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->iconButton()
                    ->size(ActionSize::Large)
                    ->color('success')
                    ->tooltip('Bejelentkezés')
                    ->visible(function (Employee $record) {
                        if (! Schema::hasTable('time_entries')) return false;

                        return ! DB::table('time_entries')
                            ->where('employee_id', $record->id)
                            ->whereNull('end_time')
                            ->whereNull('end_date')
                            ->exists();
                    })
                    ->modalWidth('sm')
                    ->form([
                        Forms\Components\Grid::make(1)->schema([
                            Forms\Components\DatePicker::make('date')->label('Dátum')->default(now())->native(false)->required(),
                            Forms\Components\TimePicker::make('time')->label('Idő')->default(now())->seconds(false)->minutesStep(5)->required(),
                        ]),
                    ])
                    ->action(function (Employee $record, array $data) {
                        if (! Schema::hasTable('time_entries')) {
                            throw new \RuntimeException('Hiányzik a time_entries tábla.');
                        }
                        $date = Carbon::parse($data['date'])->toDateString();
                        $time = Carbon::parse($data['time'])->format('H:i');

                        $uid  = Filament::auth()->id() ?? Auth::id();
                        $companyId = $record->company_id;

                        DB::table('time_entries')->insert([
                            'employee_id'  => $record->id,
                            'company_id'   => $companyId,
                            'type'         => enum_exists(TimeEntryType::class) ? TimeEntryType::Regular->value : 'work',
                            'start_date'   => $date,
                            'start_time'   => $time,
                            'end_date'     => null,
                            'end_time'     => null,
                            'hours'        => null,
                            'status'       => 'open',
                            'requested_by' => $uid,
                            'approved_by'  => $uid,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]);
                    })
                    ->successNotificationTitle('Bejelentkezve'),

                Tables\Actions\Action::make('checkOut')
                    ->label('')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->iconButton()
                    ->size(ActionSize::Large)
                    ->color('warning')
                    ->tooltip('Kijelentkezés')
                    ->visible(function (Employee $record) {
                        if (! Schema::hasTable('time_entries')) return false;

                        return DB::table('time_entries')
                            ->where('employee_id', $record->id)
                            ->whereNull('end_time')
                            ->whereNull('end_date')
                            ->exists();
                    })
                    ->modalWidth('sm')
                    ->form([
                        Forms\Components\Grid::make(1)->schema([
                            Forms\Components\DatePicker::make('date')->label('Dátum')->default(now())->native(false)->required(),
                            Forms\Components\TimePicker::make('time')->label('Idő')->default(now())->seconds(false)->minutesStep(5)->required(),
                        ]),
                    ])
                    ->action(function (Employee $record, array $data) {
                        if (! Schema::hasTable('time_entries')) {
                            throw new \RuntimeException('Hiányzik a time_entries tábla.');
                        }

                        $date = Carbon::parse($data['date'])->toDateString();
                        $time = Carbon::parse($data['time'])->format('H:i');
                        $out  = Carbon::parse("{$date} {$time}");

                        $open = DB::table('time_entries')
                            ->where('employee_id', $record->id)
                            ->whereNull('end_time')
                            ->whereNull('end_date')
                            ->orderByDesc('id')
                            ->first();

                        if (! $open) {
                            throw new \RuntimeException('Nincs nyitott jelenlét rögzítve.');
                        }

                        $in = Carbon::parse("{$open->start_date} " . (($open->start_time ?? null) ?: '08:00'));

                        // éjszakába nyúlás: ha a kijelentkezés korábbi, mint a belépés, tekintsük másnapnak
                        if ($out->lessThan($in)) {
                            $out->addDay();
                        }

                        // összes ledolgozott idő (óra, 2 tizedes)
                        $minutes    = max(0, $in->diffInMinutes($out));
                        $totalHours = round($minutes / 60, 2);

                        // Szabály:
                        // - 10:30 alatt: regular cap = 8.5h
                        // - 10:30-tól:   regular cap = 8.0h (a különbözet túlóra)
                        $threshold   = 10.5; // 10 óra 30 perc
                        $regularCap  = ($totalHours >= $threshold) ? 8.0 : 8.5;
                        $regularH    = min($totalHours, $regularCap);
                        $overtimeH   = max(0, round($totalHours - $regularH, 2));

                        $update = [
                            'end_date'   => $date,
                            'end_time'   => $time,
                            'hours'      => $totalHours,   // összes ledolgozott óra
                            'status'     => 'approved',
                            'updated_at' => now(),
                        ];

                        // Ha vannak külön mezők, mentsük őket is
                        if (Schema::hasColumn('time_entries', 'regular_hours')) {
                            $update['regular_hours'] = $regularH;
                        }
                        if (Schema::hasColumn('time_entries', 'overtime_hours')) {
                            $update['overtime_hours'] = $overtimeH;
                        }

                        DB::table('time_entries')->where('id', $open->id)->update($update);
                    })

                    ->successNotificationTitle('Kijelentkezve'),

                Tables\Actions\EditAction::make()->label(''),
                Tables\Actions\DeleteAction::make()->label(''),
                Tables\Actions\RestoreAction::make()->label(''),
                Tables\Actions\ForceDeleteAction::make()
                    ->label('Végleges törlés')
                    ->visible(fn ($record) => ($record?->trashed() ?? false)
                        && (Filament::auth()->user()?->role ?? null) === 'admin'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->label('Archiválás'),
                    Tables\Actions\RestoreBulkAction::make()->label('Visszaállítás'),
                    Tables\Actions\ForceDeleteBulkAction::make()
                        ->label('Végleges törlés')
                        ->visible(fn () => (Filament::auth()->user()?->role ?? null) === 'admin'),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    /** Visszaadja [regular_hours, overtime_hours] decimális órában. */
    protected static function splitRegularAndOvertime(float $totalHours): array
    {
        $threshold = 10.5;     // 10 óra 30 perc
        $regularCap = ($totalHours >= $threshold) ? 8.0 : 8.5;
        $regular = min($totalHours, $regularCap);
        $overtime = max(0, $totalHours - $regular);
        return [$regular, $overtime];
    }

}

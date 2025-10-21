<?php

namespace App\Filament\Resources;

use App\Enums\TimeEntryType;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\SkillsRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\VacationAllowancesRelationManager;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\ShiftPattern;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Support\Enums\ActionSize;
use Filament\Tables;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Forms\Get;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon   = 'heroicon-o-user-group';
    protected static ?string $navigationGroup  = 'Dolgozók';
    protected static ?string $navigationLabel  = 'Dolgozók';
    protected static ?string $pluralModelLabel = 'Dolgozók';
    protected static ?string $modelLabel       = 'Dolgozó';

    /** Filament panel user (fallback: Auth) */
    protected static function currentUser(): ?\App\Models\User
    {
        return Filament::auth()->user() ?? Auth::user();
    }

    public static function getRelations(): array
    {
        $rels = [];

        if (Schema::hasTable('skills') && Schema::hasTable('employee_skill')) {
            $rels[] = SkillsRelationManager::class;
        }
        if (Schema::hasTable('time_entries')) {
            $rels[] = TimeEntriesRelationManager::class;
        }
        if (Schema::hasTable('vacation_allowances')) {
            $rels[] = VacationAllowancesRelationManager::class;
        }

        return $rels;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['company', 'companies']);

        $user = static::currentUser();
        if (! $user) {
            return $query->whereRaw('1=0');
        }
        if (($user->role ?? null) === 'admin') {
            return $query;
        }

        $groupId = optional($user->company?->group)->id;

        if ($groupId) {
            return $query->where(function (Builder $w) use ($groupId) {
                $w->whereHas('company', fn (Builder $c) => $c->where('company_group_id', $groupId))
                  ->orWhereHas('companies', fn (Builder $c) => $c->where('company_group_id', $groupId));
            });
        }

        return $query->where('company_id', $user->company_id);
    }

     protected static function resolveGroupIdFromContext(?int $companyIdFromForm = null): ?int
    {
        if ($companyIdFromForm) {
            return (int) Company::whereKey($companyIdFromForm)->value('company_group_id');
        }
        $u = static::currentUser();
        return $u?->company?->company_group_id;
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        $isAdmin   = (Filament::auth()->user()?->role ?? null) === 'admin';
        $companyId = Filament::auth()->user()?->company_id;

        // Adminnak felhasználó-választó (bejelentkező user), másnak a létrehozó rejtve
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
                Forms\Components\TextInput::make('name')
                    ->label('Név')
                    ->required(),


                Forms\Components\DatePicker::make('birth_date')
                    ->label('Születési dátum')
                    ->native(false),

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
                Forms\Components\TextInput::make('phone'),

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
                    

                Forms\Components\Select::make('employment_type')
                    ->label('Foglalkoztatás')
                    ->options([
                        'full_time' => 'Teljes',
                        'part_time' => 'Részmunkaidő',
                        'casual'    => 'Alkalmi',
                    ])
                    ->required()
                    ->default('full_time'),
                                // *** MUNKÁLTATÓ (PRIMER) – ITT a fő űrlapon! ***
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

                // *** Csoporton belüli tagság (több cég) ***
                Forms\Components\Select::make('companies')
                    ->label('Válaszd ki, mely cégeknél dolgozhat (cégcsoporton belül).')
                    ->multiple()
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->relationship(name: 'companies', titleAttribute: 'name') // pivot sync
                    ->options(function (Get $get) {
                        // A tagság listát is a primer munkáltató csoportja alapján szűrjük
                        $gid = self::resolveGroupIdFromContext((int) $get('company_id'));
                        return Company::query()
                            ->when($gid, fn ($q) => $q->where('company_group_id', $gid))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    //->hint('Válaszd ki, mely cégeknél dolgozhat (cégcsoporton belül).')
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
        $groupId = optional(Filament::auth()->user()?->company?->group)->id;

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

                Tables\Columns\TextColumn::make('companies_summary')
                    ->label('Aktív cégek (tagság)')
                    ->getStateUsing(function (Employee $r) {
                        $active = $r->companies()
                            ->wherePivot('active', true)
                            ->where(fn ($w) => $w->whereNull('starts_on')->orWhere('starts_on', '<=', today()))
                            ->where(fn ($w) => $w->whereNull('ends_on')->orWhere('ends_on', '>=', today()))
                            ->pluck('companies.name')
                            ->all();

                        return $active ? implode(', ', $active) : '—';
                    })
                    ->toggleable()
                    ->hidden(),

                Tables\Columns\TextColumn::make('shiftPattern.name')
                    ->label('Műszak minta')
                    ->toggleable()
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('shiftPatternInfo')
                    ->label('Idő / Napok')
                    ->getStateUsing(fn (Employee $record) =>
                        $record->shiftPattern
                            ? "{$record->shiftPattern->start_time}–{$record->shiftPattern->end_time} • {$record->shiftPattern->days_label}"
                            : '—'
                    )
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),

                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Munkáltató (primer)')
                    ->options(fn () => Company::query()
                        ->when($groupId, fn ($q) => $q->where('company_group_id', $groupId))
                        ->orderBy('name')->pluck('name', 'id')->all()
                    ),

                Tables\Filters\SelectFilter::make('member_company')
                    ->label('Cég (tagság)')
                    ->options(fn () => Company::query()
                        ->when($groupId, fn ($q) => $q->where('company_group_id', $groupId))
                        ->orderBy('name')->pluck('name', 'id')->all()
                    )
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
            ->actionsPosition(ActionsPosition::AfterColumns)
            ->actions([
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
                            'end_date'     => null,
                            'hours'        => null,
                            'status'       => 'open',
                            'note'         => 'check-in='.$time,
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

                        $in = Carbon::parse(
                            "{$open->start_date} " . (($open->start_time ?? null) ?: '08:00')
                        );

                        $hours = max(0, round($in->diffInMinutes($out) / 60, 2));

                        DB::table('time_entries')->where('id', $open->id)->update([
                            'end_date'   => $date,
                            'end_time'   => $time,
                            'hours'      => $hours,
                            'status'     => 'approved',
                            'updated_at' => now(),
                        ]);
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
}

<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Enums\Shift;
use Filament\Tables;
use App\Models\Skill;
use Filament\Forms\Get;
use App\Models\Employee;
use App\Models\Workflow;
use App\Enums\TimeEntryType;
use Filament\Facades\Filament;
use Illuminate\Validation\Rule;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EmployeeResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\EmployeeResource\RelationManagers\TimeEntriesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\VacationAllowancesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\SkillsRelationManager;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Tabs;
use App\Models\Position;
use Filament\Forms\Components\Actions\Action as FormsAction;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\IconColumn\IconSize as TableIconSize;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Support\Enums\ActionSize;
use Filament\Tables\Enums\ActionsPosition;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use App\Models\ShiftPattern;


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

        // Skills
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
        $query = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);

        $user = static::currentUser();
        if (! $user) {
            return $query->whereRaw('1=0');
        }

        if (($user->role ?? null) === 'admin') {
            return $query;
        }

        // csak a saját cég dolgozói a tulaj (users.company_id) alapján
        return $query->whereHas('owner', fn (Builder $q) =>
            $q->where('company_id', $user->company_id)
        );
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        $isAdmin   = (Filament::auth()->user()?->role ?? null) === 'admin';
        $companyId = Filament::auth()->user()?->company_id;

        // Adminnak Select, másnak rejtett mező
        $ownerField = $isAdmin
            ? Forms\Components\Select::make('user_id')
                ->label('Tulaj (user)')
                ->relationship(
                    name: 'owner',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $query) use ($companyId) {
                        if ($companyId) {
                            $query->where('company_id', $companyId);
                        }
                        $query->orderBy('name');
                    }
                )
                ->required()
                ->searchable()
                ->preload()
            : Forms\Components\Hidden::make('user_id')
                ->default(fn () => Filament::auth()->id())
                ->dehydrated();

        return $form->schema([
            // ⬇️ Alap adatok
            Forms\Components\Section::make('Alap adatok')->schema([
                $ownerField,
                Forms\Components\TextInput::make('name')->label('Név')->required(),

                Forms\Components\DatePicker::make('birth_date')
                    ->label('Születési dátum')
                    ->native(false),

                Forms\Components\Select::make('position_id')
                    ->label('Pozíció')
                    ->native(false)
                    ->required()
                    ->searchable()
                    ->preload()

                    // hol és hogyan keressen:
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
                            ->pluck('name', 'id')   // [id => label]
                            ->toArray();
                    })

                    // kiválasztott érték címkéje (ha nem preloadolta):
                    ->getOptionLabelUsing(fn ($value) => Position::find($value)?->name)

                    // a mentés/binding a belongsTo kapcsolaton menjen:
                    ->relationship(
                        name: 'position',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            $cid = Filament::auth()->user()?->company_id;
                            $q->when($cid, fn ($q) => $q->where('company_id', $cid))
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
                    ->createOptionAction(fn (FormsAction $action) => $action->label('Új pozíció létrehozása')),

                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\Select::make('shift_pattern_id')
                    ->label('Műszak')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->visible(fn () => \Illuminate\Support\Facades\Schema::hasTable('shift_patterns')
                                && \Illuminate\Support\Facades\Schema::hasColumn('employees','shift_pattern_id'))
                    ->relationship(
                        name: 'shiftPattern',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($q) => $q->orderBy('name')
                    )
                    // Egyedi opció címke: név + időtartam + napok
                    ->getOptionLabelUsing(function ($value) {
                        $p = ShiftPattern::find($value);
                        return $p
                            ? "{$p->name} ({$p->start_time}–{$p->end_time}, {$p->days_label})"
                            : null;
                    })
                    // Legördülő listában is informatív címkék:
                    ->options(function () {
                        return ShiftPattern::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn ($p) => [
                                $p->id => "{$p->name} ({$p->start_time}–{$p->end_time}, {$p->days_label})"
                            ])->toArray();
                    })
                    ->hint('Válaszd ki a dolgozó műszakmintáját')
                    ->columnSpan(2),
                // ───── ÚJ: Jelenlét oszlop nagy ikonnal ─────
           ])->columns(4),

            // ⬇️ Skill-ek – táblázatos kijelzés (ViewField)
      /*      Forms\Components\Section::make('Skill-ek')->schema([
                Forms\Components\ViewField::make('skills_table')
                        ->view('filament.employee.skills-table')
                        ->dehydrated(false)
                        ->columnSpanFull()
                        ->visible(fn (?Employee $record) => filled($record?->id))
                        ->afterStateHydrated(function (ViewField $component, ?Employee $record) {
                            $rows = $record?->skills()
                                ->withPivot(['level', 'certified_at', 'notes'])
                                ->get() ?? collect();

                            // Itt már TÖMBÖT adunk át, nem Closurét:
                            $component->viewData(['rows' => $rows]);
                        }),
                

                // Ha később kell az űrlapos szerkesztés is, a korábbi Repeater blokk visszakapcsolható.
            ]),*/

            // ⬇️ Munkafolyamatok
            Forms\Components\Section::make('Munkafolyamatok')->schema([
                Forms\Components\Select::make('workflows')
                    ->label('Workflows')
                    ->multiple()
                    ->native(false)
                    ->relationship(
                    name: 'workflows',
                    titleAttribute: 'name',
                    modifyQueryUsing: function (Builder $q) {
                        // csak akkor szűrjünk cégre, ha tényleg van ilyen oszlop
                        if (
                            ($cid = static::currentUser()?->company_id) &&
                            \Illuminate\Support\Facades\Schema::hasColumn('workflows', 'company_id')
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
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('name')->label('Név')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('position.name')->label('Pozíció')->sortable(),
            Tables\Columns\TextColumn::make('phone')->label('Telefon')->searchable()->toggleable(),

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
           
            // Jelenlét szűrő – end_time alapján (ha nincs oszlop, visszaesik end_date-re)
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
                )
        ])

        // <<< EZ A LÉNYEG: a sor-akciók külön oszlopba kerülnek, nagy ikongombként
        ->actionsPosition(ActionsPosition::AfterColumns)

        ->actions([
            // ───── Bejelentkezés ─────
            Tables\Actions\Action::make('checkIn')
                ->label('') // csak ikon
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
                    Grid::make(1)->schema([                        // ⬅️ külön sorban
                        DatePicker::make('date')
                            ->label('Dátum')
                            ->default(now())
                            ->native(false)
                            ->required(),
                        TimePicker::make('time')
                            ->label('Idő')
                            ->default(now())
                            ->seconds(false)
                            ->minutesStep(5)
                            ->required(),
                    ]),
                ])
                ->action(function (Employee $record, array $data) {
                if (! Schema::hasTable('time_entries')) {
                    throw new \RuntimeException('Hiányzik a time_entries tábla.');
                }

                $date = Carbon::parse($data['date'])->toDateString();
                $time = Carbon::parse($data['time'])->format('H:i');

                $uid  = Filament::auth()->id() ?? Auth::id();
                $companyId = Schema::hasColumn('employees', 'company_id')
                    ? $record->company_id
                    : optional($record->owner)->company_id;

                DB::table('time_entries')->insert([
                    'employee_id'  => $record->id,
                    'company_id'   => $companyId,
                    'type'         => enum_exists(TimeEntryType::class) ? TimeEntryType::Regular->value : 'work',
                    'start_date'   => $date,          // ⬅️ külön tároljuk a dátumot
                    'end_date'     => null,
                    'hours'        => null,
                    'status'       => 'open',
                    'note'         => 'check-in='.$time, // ⬅️ az idő a megjegyzésben
                    'requested_by' => $uid,
                    'approved_by'  => $uid,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                })
                ->successNotificationTitle('Bejelentkezve'),

            // ───── Kijelentkezés ─────
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
                    Grid::make(1)->schema([
                        DatePicker::make('date')->label('Dátum')->default(now())->native(false)->required(),
                        TimePicker::make('time')->label('Idő')->default(now())->seconds(false)->minutesStep(5)->required(),
                    ]),
                ])
                ->action(function (Employee $record, array $data) {
                    if (! Schema::hasTable('time_entries')) {
                        throw new \RuntimeException('Hiányzik a time_entries tábla.');
                    }

                    $date = Carbon::parse($data['date'])->toDateString();
                    $time = Carbon::parse($data['time'])->format('H:i');
                    $out  = Carbon::parse("{$date} {$time}");

                    // Nyitott jelenlét (legutolsó)
                    $open = DB::table('time_entries')
                        ->where('employee_id', $record->id)
                        ->whereNull('end_time')   // ⬅ mindkettő kell
                        ->whereNull('end_date')
                        ->orderByDesc('id')
                        ->first();

                    if (! $open) {
                        throw new \RuntimeException('Nincs nyitott jelenlét rögzítve.');
                    }

                    // belépés dátum+idő (fallback 08:00, ha a time nincs kitöltve)
                    $in = Carbon::parse(
                        "{$open->start_date} " . (($open->start_time ?? null) ?: '08:00')
                    );

                    $hours = max(0, round($in->diffInMinutes($out) / 60, 2));

                    DB::table('time_entries')->where('id', $open->id)->update([
                        'end_date'   => $date,
                        'end_time'   => $time,   // ⬅️ ÚJ
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

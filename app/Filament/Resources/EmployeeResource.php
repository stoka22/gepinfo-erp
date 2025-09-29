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
                    ->relationship(
                        name: 'position',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query) {
                            if ($cid = Filament::auth()->user()?->company_id) {
                                $query->where('company_id', $cid)->where('active', true);
                            }
                            $query->orderBy('name');
                        }
                    )
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(100),
                        Forms\Components\TextInput::make('code')->label('Kód')->maxLength(50),
                        Forms\Components\Toggle::make('active')->label('Aktív')->default(true),
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn () => Filament::auth()->user()?->company_id)
                            ->dehydrated(),
                    ])
                    ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                        $action->label('Új pozíció létrehozása');
                    })
                    ->required()
                    ->preload()
                    ->searchable(),

                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\DatePicker::make('hired_at')->label('Felvétel dátuma'),

                Forms\Components\Select::make('shift')
                    ->label('Műszak')
                    ->options([
                        Shift::Morning->value   => 'Délelőtt',
                        Shift::Afternoon->value => 'Délután',
                        Shift::Night->value     => 'Éjszaka',
                    ])
                    ->required()
                    ->native(false),
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
                    ->relationship(
                        name: 'workflows',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            $cid = static::currentUser()?->company_id;
                            if ($cid && Schema::hasColumn('workflows', 'company_id')) {
                                $q->where('company_id', $cid);
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

                BadgeColumn::make('shift')
                    ->label('Műszak')
                    ->state(fn ($record) =>
                        $record->shift instanceof \BackedEnum ? $record->shift->value : $record->shift
                    )
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'morning'   => 'Délelőtt',
                        'afternoon' => 'Délután',
                        'night'     => 'Éjszaka',
                        default     => ($state ? ucfirst($state) : '—'),
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'morning'   => 'warning',
                        'afternoon' => 'info',
                        'night'     => 'danger',
                        default     => 'gray',
                    })
                    ->sortable(
                        query: fn (Builder $q, string $direction) =>
                            $q->orderByRaw("FIELD(shift,'morning','afternoon','night') {$direction}")
                    ),

                Tables\Columns\TextColumn::make('created_at')->since()->label('Létrehozva')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Módosítva')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')->since()->label('Archiválva')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('shift')
                    ->label('Műszak')
                    ->options([
                        'morning'   => 'Délelőtt',
                        'afternoon' => 'Délután',
                        'night'     => 'Éjszaka',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('presence')
                    ->label('Jelenlét')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Employee $record) {
                        if (! Schema::hasTable('time_entries')) {
                            throw new \RuntimeException('Hiányzik a time_entries tábla.');
                        }
                        $uid   = Filament::auth()->id() ?? Auth::id();
                        $today = now()->toDateString();

                        $shift = $record->shift instanceof \BackedEnum ? $record->shift->value : ($record->shift ?? null);
                        $shiftHu = match ($shift) {
                            'morning'   => 'Délelőtt',
                            'afternoon' => 'Délután',
                            'night'     => 'Éjszaka',
                            default     => 'Ismeretlen',
                        };

                        $companyId = Schema::hasColumn('employees', 'company_id')
                            ? $record->company_id
                            : optional($record->owner)->company_id;

                        DB::table('time_entries')->updateOrInsert(
                            [
                                'employee_id' => $record->id,
                                'start_date'  => $today,
                                'type'        => TimeEntryType::Regular->value,
                            ],
                            [
                                'company_id'   => $companyId,
                                'end_date'     => $today,
                                'hours'        => 8.0,
                                'status'       => 'approved',
                                'note'         => 'Jelenlét rögzítve – műszak: '.$shiftHu,
                                'requested_by' => $uid,
                                'approved_by'  => $uid,
                                'updated_at'   => now(),
                                'created_at'   => now(),
                            ]
                        );
                    })
                    ->successNotificationTitle('Jelenlét rögzítve (8 óra ma)'),
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
                    Tables\Actions\BulkAction::make('presenceBulk')
                        ->label('Jelenlét rögzítése (ma, 8 óra)')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (array $records) {
                            $uid = Filament::auth()->id() ?? Auth::id();
                            $today = now()->toDateString();
                            foreach ($records as $record) {
                                /** @var Employee $record */
                                $shift = $record->shift instanceof \BackedEnum ? $record->shift->value : ($record->shift ?? null);
                                $shiftHu = match ($shift) {
                                    'morning' => 'Délelőtt',
                                    'afternoon' => 'Délután',
                                    'night' => 'Éjszaka',
                                    default => 'Ismeretlen',
                                };
                                $companyId = Schema::hasColumn('employees', 'company_id')
                                    ? $record->company_id
                                    : optional($record->owner)->company_id;

                                DB::table('time_entries')->updateOrInsert(
                                    [
                                        'employee_id' => $record->id,
                                        'start_date'  => $today,
                                        'type'        => 'work',
                                    ],
                                    [
                                        'company_id'   => $companyId,
                                        'end_date'     => $today,
                                        'hours'        => 8.0,
                                        'status'       => 'approved',
                                        'note'         => 'Jelenlét rögzítve – műszak: ' . $shiftHu,
                                        'requested_by' => $uid,
                                        'approved_by'  => $uid,
                                        'updated_at'   => now(),
                                        'created_at'   => now(),
                                    ]
                                );
                            }
                        })
                        ->successNotificationTitle('Jelenlét rögzítve a kijelöltekre'),
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

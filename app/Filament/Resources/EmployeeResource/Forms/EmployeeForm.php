<?php

namespace App\Filament\Resources\EmployeeResource\Forms;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\Company;
use Filament\Forms\Get;
use App\Models\Employee;
use App\Models\Position;
use App\Models\ShiftPattern;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EmployeeResource;

class EmployeeForm
{
    protected static function normalizeDate(null|string|\DateTimeInterface $value, string $out = 'Y-m-d'): ?string
    {
        if (!$value) return null;
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format($out);
        }
        $s = trim((string)$value);
        $formats = [
            'Y-m-d',
            'Y.m.d',
            'Y. m. d.',
            'Y/m/d',
            'd.m.Y',
            'd-m-Y',
            'd/m/Y',
            'Y. F d.',
            'Y. M d.',
        ];
        foreach ($formats as $f) {
            try {
                return Carbon::createFromFormat($f, $s)->format($out);
            } catch (\Throwable) {
            }
        }
        try {
            return Carbon::parse($s)->format($out);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function resolveGroupIdFromContext(?int $companyIdFromForm = null): ?int
    {
        if ($companyIdFromForm) {
            return (int) Company::whereKey($companyIdFromForm)->value('company_group_id');
        }
        return EmployeeResource::currentGroupId();
    }

    public static function configure(Forms\Form $form): Forms\Form
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
                    $query->when($companyId, fn($q) => $q->where('company_id', $companyId))
                        ->orderBy('name');
                }
            )
            ->searchable()
            ->preload()
            ->native(false)
            ->placeholder('— nincs hozzárendelve —')
            : Forms\Components\Hidden::make('created_by_user_id')
            ->default(fn() => Filament::auth()->id())
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
                    ->dehydrateStateUsing(fn($state) => static::normalizeDate($state, 'Y-m-d'))
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
                            ->when($cid, fn($q) => $q->where('company_id', $cid))
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
                    ->getOptionLabelUsing(fn($value) => Position::find($value)?->name)
                    ->relationship(
                        name: 'position',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $q) {
                            $cid = Filament::auth()->user()?->company_id;
                            $q->when($cid, fn($qq) => $qq->where('company_id', $cid))
                                ->where('active', true)
                                ->orderBy('name');
                        }
                    )
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->label('Név')->required()->maxLength(100),
                        Forms\Components\TextInput::make('code')->label('Kód')->maxLength(50),
                        Forms\Components\Toggle::make('active')->label('Aktív')->default(true),
                        Forms\Components\Hidden::make('company_id')
                            ->default(fn() => Filament::auth()->user()?->company_id)
                            ->dehydrated(true),
                    ])
                    ->createOptionAction(fn(Forms\Components\Actions\Action $action) => $action->label('Új pozíció létrehozása')),
                Forms\Components\Select::make('position')
                    ->label('Terület')
                    //->required()
                    ->searchable()
                    ->options(
                        Employee::query()
                            ->distinct()
                            ->orderBy('position')
                            ->pluck('position', 'position')
                            ->filter()
                            ->toArray()
                    )
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('add_position')
                            ->label('Új terület')
                            ->icon('heroicon-o-plus')
                            ->form([
                                Forms\Components\TextInput::make('new_position')
                                    ->label('Terület neve')
                                    ->required(),
                            ])
                            ->action(function (array $data, Forms\Components\Select $component) {
                                // az új értéket hozzáadjuk az options-hoz
                                $new = $data['new_position'];

                                $options = $component->getOptions();
                                $options[$new] = $new;
                                $component->options($options);

                                // és beállítjuk kiválasztott értéknek
                                $component->state($new);
                            })
                    ),
                Forms\Components\TextInput::make('email')->email(),

                Forms\Components\Select::make('employment_type')
                    ->label('Foglalkoztatás')
                    ->options([
                        'full_time' => 'Teljes',
                        'part_time' => 'Részmunkaidő',
                        'casual'    => 'Alkalmi',
                    ])->required()->default('full_time'),

                Forms\Components\Select::make('daily_quota_hours')
                    ->label('Napi kötelező munkaidő')
                    ->helperText('Ebből számol a túlóra-motor: +30 perc puffer, +10 perc türelmi idő fölött keletkezik túlóra.')
                    ->options([
                        '4.00' => '4 óra',
                        '6.00' => '6 óra',
                        '8.00' => '8 óra',
                    ])
                    ->default('8.00')
                    ->required(),

                Forms\Components\Select::make('shift_pattern_id')
                    ->label('Műszak')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->visible(fn() => Schema::hasTable('shift_patterns') && Schema::hasColumn('employees', 'shift_pattern_id'))
                    ->relationship(
                        name: 'shiftPattern',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn($q) => $q->orderBy('name')
                    )
                    ->getOptionLabelUsing(function ($value) {
                        $p = ShiftPattern::find($value);
                        return $p ? "{$p->name} ({$p->start_time}–{$p->end_time}, {$p->days_label})" : null;
                    })
                    ->options(function () {
                        return ShiftPattern::query()
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn($p) => [$p->id => "{$p->name} ({$p->start_time}–{$p->end_time}, {$p->days_label})"])
                            ->toArray();
                    })
                    ->placeholder('Válaszd ki a dolgozó műszakmintáját')
                    ->columnSpan(2),
                Forms\Components\DatePicker::make('hired_at')
                    ->label('Felvétel dátuma')
                    ->native(false)
                    ->displayFormat('Y. m. d.')
                    ->format('Y-m-d')
                    ->default(fn() => now()->toDateString())   // alapértelmezett: ma
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
                    ->dehydrateStateUsing(fn($state) => static::normalizeDate($state, 'Y-m-d'))
                    ->rule('date'),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\TextInput::make('rfid')
                    ->label('Kártyszám'),

                Forms\Components\Select::make('company_id')
                    ->label('Munkáltató (primer)')
                    ->visible(fn() => Schema::hasColumn('employees', 'company_id'))
                    ->relationship(name: 'company', titleAttribute: 'name')
                    ->options(function (Get $get) {
                        $gid = self::resolveGroupIdFromContext((int) $get('company_id'));
                        return Company::query()
                            ->when($gid, fn($q) => $q->where('company_group_id', $gid))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->getOptionLabelUsing(fn($value) => Company::find($value)?->name)
                    ->afterStateHydrated(function (callable $set, ?Employee $record) {
                        $set('company_id', $record?->company_id);
                    })
                    ->default(fn() => Filament::auth()->user()?->company_id)
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
                            ->when($gid, fn($q) => $q->where('company_group_id', $gid))
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
                                ($cid = EmployeeResource::currentUser()?->company_id) &&
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
}

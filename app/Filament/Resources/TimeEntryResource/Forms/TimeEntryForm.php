<?php

namespace App\Filament\Resources\TimeEntryResource\Forms;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntryType;
use App\Models\Employee;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Filament\Resources\TimeEntryResource;

class TimeEntryForm
{
    /** presence típushoz: hours számítása és status beállítása */
    protected static function recalcPresence(Set $set, Get $get): void
    {
        $type = $get('type') instanceof \BackedEnum ? $get('type')->value : $get('type');
        if ($type !== 'presence') {
            return;
        }

        $sd = $get('start_date');
        $st = $get('start_time');
        $ed = $get('end_date') ?: $sd;
        $et = $get('end_time');

        if (!$sd || !$st) {
            // nincs belépési dátum/idő → ne számoljunk
            $set('hours', null);
            return;
        }

        $in  = \Carbon\Carbon::parse("{$sd} " . ($st ?: '00:00'));
        $out = $et ? \Carbon\Carbon::parse("{$ed} {$et}") : null;

        if ($out && $out->lessThan($in)) {
            // éjszakába nyúlás: másnap
            $out->addDay();
        }

        if ($out) {
            $minutes = max(0, $in->diffInMinutes($out));
            $hours   = round($minutes / 60, 2);
            $set('hours', $hours);
            // státusz szinkron
            $set('status', TimeEntryStatus::CheckedOut->value);
        } else {
            // csak belépés ismert
            $set('hours', 0.00);
            $set('status', TimeEntryStatus::CheckedIn->value);
        }
    }

    public static function configure(Forms\Form $form): Forms\Form
    {
        $groupIds = TimeEntryResource::companyGroupIds();

        return $form->schema([
            Forms\Components\Section::make()->schema([

              /*  Forms\Components\Hidden::make('company_id')
                    ->default(fn () => Auth::user()?->company_id)
                    ->dehydrated(fn ($state) => filled($state)),
*/
                Forms\Components\Select::make('employee_id')
                    ->label('Dolgozó')
                    ->searchable()
                    ->optionsLimit(1000)
                    ->getSearchResultsUsing(function (string $search): array {
                        return Employee::query()
                            ->whereIn('company_id', TimeEntryResource::accessibleCompanyIds())
                            ->when(
                                filled($search),
                                fn ($q) => $q->where('name', 'like', "%{$search}%")
                            )
                            ->orderBy('name')
                            ->limit(1000)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->getOptionLabelUsing(fn ($value): ?string =>
                        Employee::query()->whereKey($value)->value('name')
                    )
                    ->afterStateUpdated(function ($state, Set $set) {
                        $employee = Employee::query()->find($state);
                        $set('company_id', $employee?->company_id);
                    })
                    ->required(),

                Forms\Components\Select::make('type')
                    ->label('Típus')
                    ->options([
                        TimeEntryType::Presence->value  => 'Jelenlét',
                        TimeEntryType::Vacation->value  => 'Szabadság',
                        TimeEntryType::Overtime->value  => 'Túlóra',
                        TimeEntryType::SickLeave->value => 'Táppénz',
                    ])
                    ->default(TimeEntryType::Presence->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        $type = $state instanceof \BackedEnum ? $state->value : $state;

                        if ($type === TimeEntryType::Presence->value) {
                            $set('status', TimeEntryStatus::CheckedIn->value);
                        } else {
                            $set('status', TimeEntryStatus::Pending->value);
                            $set('start_time', null);
                            $set('end_time', null);

                            if ($type !== TimeEntryType::Overtime->value) {
                                $set('hours', null);
                            }
                        }

                        static::recalcPresence($set, $get);
                    }),

                // ⬇ EGY mező: a type-tól függően tölti fel az opciókat
                Forms\Components\Select::make('status')
                    ->label(fn (Forms\Get $get) => $get('type') === TimeEntryType::Presence->value
                        ? 'Jelenlét státusz'
                        : 'Jóváhagyási státusz')
                    ->options(function (Forms\Get $get) {
                        return $get('type') === TimeEntryType::Presence->value
                            ? [
                                TimeEntryStatus::CheckedIn->value  => 'Bejelentkezve',
                                TimeEntryStatus::CheckedOut->value => 'Kijelentkezve',
                              ]
                            : [
                                TimeEntryStatus::Pending->value  => 'Függőben',
                                TimeEntryStatus::Approved->value => 'Jóváhagyva',
                                TimeEntryStatus::Rejected->value => 'Elutasítva',
                              ];
                    })
                    ->default(fn (Forms\Get $get) => $get('type') === TimeEntryType::Presence->value
                        ? TimeEntryStatus::CheckedIn->value
                        : TimeEntryStatus::Pending->value)
                    ->required(),

                Forms\Components\DatePicker::make('start_date')
                    ->label('Kezdet')
                    ->required()
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalcPresence($set, $get)),

                Forms\Components\DatePicker::make('end_date')
                    ->label('Vége')
                    ->visible(fn (Get $get) =>
                        ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) !== 'overtime'
                        && ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) !== 'presence'
                    )
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalcPresence($set, $get))
                    ->afterOrEqual('start_date'),

                Forms\Components\TimePicker::make('start_time')
                    ->label('Belépés ideje')
                    ->seconds(false)
                    ->minutesStep(5)
                    ->visible(fn (Get $get) =>
                        ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === 'presence'
                    )
                    ->required(fn (Get $get) =>
                        ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === 'presence'
                    )
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalcPresence($set, $get)),

                /** ÚJ: kilépési idő – csak presence */
                Forms\Components\TimePicker::make('end_time')
                    ->label('Kilépés ideje')
                    ->seconds(false)
                    ->minutesStep(5)
                    ->visible(fn (Get $get) =>
                        ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === 'presence'
                    )
                    ->afterStateUpdated(fn ($state, Set $set, Get $get) => static::recalcPresence($set, $get)),

                Forms\Components\TextInput::make('hours')
                    ->label('Órák')
                    ->numeric()
                    ->minValue(0.25)
                    ->step(0.25)
                    ->dehydrated(true) // <— FONTOS: rejtve is mentjük (presence esetén a kalkulált értéket)
                    ->visible(fn (Get $get) =>
                        ($get('type') instanceof \BackedEnum ? $get('type')->value : $get('type')) === 'overtime'
                    ),

                Forms\Components\Textarea::make('note')
                    ->label('Megjegyzés')
                    ->rows(3),

                Forms\Components\Hidden::make('company_id')
                     ->dehydrated(true),
                Forms\Components\Hidden::make('requested_by')
                    ->default(fn () => Auth::id())
                    ->dehydrated(true),
            ])->columns(2),
        ]);
    }
}

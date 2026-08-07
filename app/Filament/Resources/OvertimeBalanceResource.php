<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OvertimeBalanceResource\Pages;
use App\Models\Company;
use App\Models\OvertimeBalance;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

class OvertimeBalanceResource extends Resource
{
    protected static ?string $model = OvertimeBalance::class;

    protected static ?string $navigationIcon  = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Kimutatások';
    protected static ?string $navigationLabel = 'Túlóra-egyenlegek';
    protected static ?string $modelLabel      = 'Túlóra-egyenleg';
    protected static ?string $pluralLabel     = 'Túlóra-egyenlegek';

    public static function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) Auth::user()?->hasRole('admin');
    }

    public static function canCreate(): bool
    {
        return false; // levezetett adat, a rendszer maga tartja karban (TimeEntryObserver)
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Dolgozó')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Cég')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('balance_minutes')
                    ->label('Automatikus egyenleg')
                    ->formatStateUsing(fn (int $state) => static::formatMinutes($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('manual_adjustment_minutes')
                    ->label('Kézi korrekció')
                    ->formatStateUsing(fn (int $state) => static::formatMinutes($state))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('effective_balance_minutes')
                    ->label('Tényleges egyenleg')
                    ->state(fn (OvertimeBalance $record) => $record->effective_balance_minutes)
                    ->formatStateUsing(fn (int $state) => static::formatMinutes($state))
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state < 0  => 'danger',
                        $state > 0  => 'success',
                        default     => 'gray',
                    })
                    ->sortable(query: fn ($query, string $direction) => $query
                        ->orderByRaw('(balance_minutes + manual_adjustment_minutes) '.$direction)),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Frissítve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('company_id')
                    ->label('Cég')
                    ->relationship('company', 'name'),

                Tables\Filters\Filter::make('deficit')
                    ->label('Csak hiányban lévők (negatív egyenleg)')
                    ->query(fn ($query) => $query->whereRaw('(balance_minutes + manual_adjustment_minutes) < 0')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->tooltip('Kézi korrekció szerkesztése')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('manual_adjustment_minutes')
                            ->label('Kézi korrekció (perc)')
                            ->numeric()
                            ->required()
                            ->helperText('Pozitív = jóváírás, negatív = levonás. Az automatikus egyenleget nem módosítja.'),
                    ]),
            ])
            // legnagyobb hiány elöl (a legsürgetőbb figyelemre méltó) — closure, mert
            // effective_balance_minutes nem valódi DB-oszlop, csak levezetett accessor.
            ->defaultSort(fn ($query) => $query->orderByRaw('(balance_minutes + manual_adjustment_minutes) asc'));
    }

    protected static function formatMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $abs = abs($minutes);
        $h = intdiv($abs, 60);
        $m = $abs % 60;
        return sprintf('%s%d:%02d', $sign, $h, $m);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvertimeBalances::route('/'),
        ];
    }
}

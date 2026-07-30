<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CardResource\Pages;
use App\Models\Card;
use App\Models\Employee;
use App\Services\CardService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Validation\ValidationException;

class CardResource extends Resource
{
    protected static ?string $model = Card::class;
    protected static ?string $navigationGroup = 'Dolgozók';
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Kártyák';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('uid')->required()->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('label')->maxLength(120),
            Forms\Components\Select::make('status')
                ->options(['available'=>'Szabad','assigned'=>'Hozzárendelve','lost'=>'Elveszett','blocked'=>'Blokkolt'])
                ->required(),
            Forms\Components\Textarea::make('notes')->rows(3),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uid')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('employee.name')->label('Dolgozó')->toggleable(),
                Tables\Columns\TextColumn::make('assigned_at')->dateTime()->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('assignToEmployee')
                    ->label('')
                    ->icon('heroicon-o-user-plus')
                    ->tooltip('Hozzárendelés dolgozóhoz')
                    ->color('success')
                    ->visible(fn (Card $record) => ! $record->employee_id)
                    ->form([
                        Forms\Components\Select::make('employee_id')
                            ->label('Dolgozó')
                            ->options(fn () => Employee::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Card $record, array $data) {
                        try {
                            app(CardService::class)->assignByUid($data['employee_id'], $record->uid);
                            Notification::make()->title('Kártya hozzárendelve.')->success()->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Nem sikerült hozzárendelni')
                                ->body(collect($e->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('unassignFromEmployee')
                    ->label('')
                    ->icon('heroicon-o-user-minus')
                    ->tooltip('Leválasztás a dolgozóról')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Card $record) => (bool) $record->employee_id)
                    ->action(function (Card $record) {
                        app(CardService::class)->unassign($record->id);
                        Notification::make()->title('Kártya leválasztva.')->success()->send();
                    }),

                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCards::route('/'),
            'create' => Pages\CreateCard::route('/create'),
            'edit' => Pages\EditCard::route('/{record}/edit'),
        ];
    }
}

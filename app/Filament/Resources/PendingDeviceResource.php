<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PendingDeviceResource\Pages;
use App\Models\PendingDevice;
use App\Models\Device;
use App\Models\User;
use App\Models\Machine;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PendingDeviceResource extends Resource
{
    protected static ?string $model = PendingDevice::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock'; // várakozó ikon
    protected static ?string $navigationGroup = 'Eszközök'; 
    protected static ?string $navigationLabel = 'Várakozó eszközök';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('mac_address')->label('MAC')->required()->maxLength(255),
            Forms\Components\TextInput::make('proposed_name')->label('Javasolt név')->maxLength(255),
            Forms\Components\TextInput::make('fw_version')->label('FW')->maxLength(255),
            Forms\Components\TextInput::make('ip')->label('IP')->maxLength(255),
            Forms\Components\DateTimePicker::make('last_seen_at')->label('Utolsó jel'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('mac_address')->label('MAC')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('proposed_name')->label('Javasolt név')->searchable(),
                Tables\Columns\TextColumn::make('fw_version')->label('FW')->searchable(),
                Tables\Columns\TextColumn::make('ip')->label('IP')->searchable(),
                Tables\Columns\TextColumn::make('last_seen_at')->label('Utolsó jel')->dateTime()->since()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Felvéve')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Módosítva')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->actions([
                // Ha nem akarod szerkeszthetőnek: vedd ki a következő sort
                // Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label('Jóváhagyás')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Felhasználó')
                            ->options(fn() => User::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()->preload()->required(),

                        Forms\Components\Select::make('machine_id')
                            ->label('Gép')
                            ->options(fn() => Machine::orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()->preload(),

                        Forms\Components\TextInput::make('name')
                            ->label('Eszköz neve')
                            ->placeholder('Ha üres, a javasolt név vagy MAC alapján töltjük'),
                    ])
                    ->action(function (array $data, PendingDevice $record) {
                        $device = Device::create([
                            'user_id'      => $data['user_id'],
                            'machine_id'   => $data['machine_id'] ?? null,
                            'name'         => $data['name'] ?: ($record->proposed_name ?: ('Device '.$record->mac_address)),
                            'mac_address'  => $record->mac_address,
                            'location'     => null,
                            'device_token' => Str::random(48),
                        ]);

                        $record->delete();

                        Notification::make()
                            ->title('Eszköz jóváhagyva')
                            ->body('Token: '.$device->device_token)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPendingDevices::route('/'),
            'create' => Pages\CreatePendingDevice::route('/create'), // ha nem kell kézi felvétel: töröld ezt
            'edit'   => Pages\EditPendingDevice::route('/{record}/edit'), // ha nem kell szerkesztés: töröld ezt
        ];
    }
}

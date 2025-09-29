<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceFileResource\Pages;
use App\Models\DeviceFile;

use Filament\Forms;
use Filament\Forms\Components\{Select, TextInput, FileUpload};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DeviceFileResource extends Resource
{
    protected static ?string $model = DeviceFile::class;

    protected static ?string $navigationIcon  = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Eszköz fájlok';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('device_id')
                ->label('Eszköz')
                ->relationship('device', 'name')
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('title')
                ->label('Cím')
                ->maxLength(150),

            Select::make('kind')
                ->label('Típus')
                ->options([
                    'log'    => 'Log',
                    'photo'  => 'Fotó',
                    'config' => 'Konfiguráció',
                    'doc'    => 'Dokumentum',
                    'other'  => 'Egyéb',
                ])
                ->default('other'),

            FileUpload::make('file_path')
                ->label('Fájl')
                ->directory('device-files')
                ->disk('public')        // storage/app/public/device-files
                ->visibility('public')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->required(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.name')
                    ->label('Eszköz')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Cím')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('kind')
                    ->label('Típus')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state) => match ($state) {
                        'log'    => 'gray',
                        'photo'  => 'info',
                        'config' => 'warning',
                        'doc'    => 'success',
                        default  => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Feltöltve')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('download')
                    ->label('Letöltés')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (DeviceFile $record): string => Storage::disk('public')->url($record->file_path))
                    ->openUrlInNewTab()
                    ->visible(fn (DeviceFile $record): bool =>
                        filled($record->file_path) && Storage::disk('public')->exists($record->file_path)
                    ),

                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeviceFiles::route('/'),
            'create' => Pages\CreateDeviceFile::route('/create'),
            'edit'   => Pages\EditDeviceFile::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Illuminate\Support\Facades\Storage;

class DeviceFilesRelationManager extends RelationManager
{
    // A Device modellen a kapcsolat neve:
    protected static string $relationship = 'deviceFiles';

    // Címke mező (a generatornál is ezt adtuk meg)
    protected static ?string $recordTitleAttribute = 'title';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->label('Cím')
                ->maxLength(120),

            Forms\Components\Select::make('kind')
                ->label('Típus')
                ->options([
                    'log'   => 'Log',
                    'photo' => 'Fotó',
                    'config'=> 'Konfiguráció',
                    'doc'   => 'Dokumentum',
                    'other' => 'Egyéb',
                ])
                ->default('other')
                ->required(),

            Forms\Components\FileUpload::make('file_path')
                ->label('Fájl')
                ->directory('device-files')
                ->required()
                ->preserveFilenames()
                ->openable()
                ->downloadable(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Cím')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('kind')->label('Típus')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Feltöltve')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Letöltés')
                    ->url(fn ($record) => Storage::url($record->file_path))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}

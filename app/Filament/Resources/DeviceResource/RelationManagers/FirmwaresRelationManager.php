<?php

namespace App\Filament\Resources\DeviceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Components\{TextInput,Textarea,Toggle,DateTimePicker,FileUpload};

class FirmwaresRelationManager extends RelationManager
{
    protected static string $relationship = 'firmwares'; // Device::firmwares()

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('version')->required()->label('Verzió'),
            TextInput::make('build')->numeric()->minValue(1)->default(1),

            FileUpload::make('file_path')
                ->label('Firmware fájl')
                ->directory('firmware')
                ->required()
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->acceptedFileTypes(['application/octet-stream','.bin','.uf2','.zip'])
                ->maxSize(100*1024)
                ->getUploadedFileNameForStorageUsing(fn ($file) =>
                    now()->format('Ymd_His') . '_' . str($file->getClientOriginalName())->slug('_')
                ),

            Toggle::make('forced')->label('Kötelező frissítés'),
            DateTimePicker::make('published_at')->label('Kiadás ideje')->seconds(false),
            Textarea::make('notes')->rows(3)->label('Megjegyzés'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version')->badge()->sortable()->searchable(),
                Tables\Columns\TextColumn::make('build')->sortable(),
                Tables\Columns\IconColumn::make('forced')->boolean(),
                Tables\Columns\TextColumn::make('published_at')->dateTime('Y-m-d H:i'),
            ])
            ->headerActions([ Tables\Actions\CreateAction::make() ])
            ->actions([ Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make() ])
            ->defaultSort('published_at','desc');
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingMaterialResource\Pages;
use App\Models\TrainingMaterial;
use Filament\Forms\Components\{FileUpload, Select, Textarea, TextInput, Toggle};
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class TrainingMaterialResource extends Resource
{
    protected static ?string $model = TrainingMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationGroup = 'Oktatás';
    protected static ?string $navigationLabel = 'Oktatási anyagok';
    protected static ?string $modelLabel = 'oktatási anyag';
    protected static ?string $pluralModelLabel = 'Oktatási anyagok';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('kind')
                ->label('Típus')
                ->options([
                    'file' => 'Fájl (táblázat, szoftver, dokumentum)',
                    'video' => 'Videó (YouTube)',
                ])
                ->required()
                ->live()
                ->default('file'),

            TextInput::make('title')
                ->label('Cím')
                ->required()
                ->maxLength(150)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Leírás')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('category')
                ->label('Kategória')
                ->options(TrainingMaterial::CATEGORIES)
                ->visible(fn (Get $get) => $get('kind') === 'file')
                ->required(fn (Get $get) => $get('kind') === 'file'),

            FileUpload::make('file_path')
                ->label('Fájl')
                ->directory('oktatas')
                ->disk('public')
                ->visibility('public')
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->maxSize(51200) // 50 MB
                ->visible(fn (Get $get) => $get('kind') === 'file')
                ->required(fn (Get $get) => $get('kind') === 'file'),

            TextInput::make('youtube_url')
                ->label('YouTube link')
                ->url()
                ->helperText('Pl.: https://www.youtube.com/watch?v=... vagy https://youtu.be/...')
                ->visible(fn (Get $get) => $get('kind') === 'video')
                ->required(fn (Get $get) => $get('kind') === 'video'),

            TextInput::make('sort_order')
                ->label('Sorrend')
                ->numeric()
                ->default(0)
                ->helperText('Kisebb szám kerül előrébb a listában.'),

            Toggle::make('is_published')
                ->label('Publikus (megjelenik a honlapon)')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Cím')
                    ->searchable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('kind')
                    ->label('Típus')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'file' ? 'Fájl' : 'Videó')
                    ->color(fn (string $state) => $state === 'file' ? 'info' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategória')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (TrainingMaterial::CATEGORIES[$state] ?? $state) : '—')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('file_size_for_humans')
                    ->label('Méret'),

                Tables\Columns\ToggleColumn::make('is_published')
                    ->label('Publikus'),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Sorrend')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Létrehozva')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kind')
                    ->label('Típus')
                    ->options([
                        'file' => 'Fájl',
                        'video' => 'Videó',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),

                Tables\Actions\Action::make('download')
                    ->label('')
                    ->tooltip('Letöltés')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (TrainingMaterial $record): string => route('oktatas.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (TrainingMaterial $record): bool =>
                        $record->kind === 'file' && filled($record->file_path) && Storage::disk('public')->exists($record->file_path)
                    ),

                Tables\Actions\Action::make('watch')
                    ->label('')
                    ->tooltip('Megnyitás YouTube-on')
                    ->icon('heroicon-o-play')
                    ->url(fn (TrainingMaterial $record): ?string => $record->youtube_url)
                    ->openUrlInNewTab()
                    ->visible(fn (TrainingMaterial $record): bool => $record->kind === 'video' && filled($record->youtube_url)),

                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
            ])
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainingMaterials::route('/'),
            'create' => Pages\CreateTrainingMaterial::route('/create'),
            'edit' => Pages\EditTrainingMaterial::route('/{record}/edit'),
        ];
    }
}

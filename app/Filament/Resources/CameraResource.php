<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CameraResource\Pages;
use App\Models\Camera;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CameraResource extends Resource
{
    protected static ?string $model = Camera::class;

    protected static ?string $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Kamerák (kezelés)';

    public static function shouldRegisterNavigation(): bool
    {
        $u = Auth::user();
        return $u?->hasRole('admin') || $u?->can('cameras.viewAny');
    }

    public static function canViewAny(): bool { return static::shouldRegisterNavigation(); }
    public static function canCreate(): bool { return static::shouldRegisterNavigation(); }
    public static function canEdit($record): bool { return static::shouldRegisterNavigation(); }
    public static function canView($record): bool { return static::shouldRegisterNavigation(); }
    public static function canDelete($record): bool { return static::shouldRegisterNavigation(); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Hidden::make('company_id')
                ->default(fn () => Auth::user()?->company_id)
                ->dehydrated(fn ($state) => filled($state)),

            Forms\Components\TextInput::make('name')
                ->label('Név')
                ->required(),

            Forms\Components\TextInput::make('stream_url')
                ->label('Stream URL (HLS .m3u8)')
                ->required()
                ->url(),

            Forms\Components\TextInput::make('sort_order')
                ->label('Sorrend')
                ->numeric()
                ->default(0),

            Forms\Components\Toggle::make('is_active')
                ->label('Aktív')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Név')->searchable(),
                TextColumn::make('stream_url')->label('Stream URL')->limit(40)->copyable(),
                TextColumn::make('sort_order')->label('Sorrend')->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktív')
                    ->alignCenter()
                    ->onColor('success')
                    ->offColor('gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('')
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->tooltip('Szerkesztés'),
                Tables\Actions\DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->tooltip('Törlés'),
            ])
            ->defaultSort('sort_order');
    }

    public static function getEloquentQuery(): Builder
    {
        $q = parent::getEloquentQuery();

        if (Auth::check() && Auth::user()->company_id) {
            $q->where('company_id', Auth::user()->company_id);
        }

        return $q;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCameras::route('/'),
            'create' => Pages\CreateCamera::route('/create'),
            'edit' => Pages\EditCamera::route('/{record}/edit'),
        ];
    }
}

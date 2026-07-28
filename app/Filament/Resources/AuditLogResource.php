<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon  = 'heroicon-o-document-magnifying-glass';
    protected static ?string $navigationGroup = 'Törzsadatok';
    protected static ?string $navigationLabel = 'Változás-napló';
    protected static ?string $modelLabel      = 'Naplóbejegyzés';
    protected static ?string $pluralLabel     = 'Változás-napló';

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
        return false; // csak olvasható napló
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Időpont')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Tábla')
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('Rekord ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Esemény')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'created' => 'Létrehozva',
                        'updated' => 'Módosítva',
                        'deleted' => 'Törölve',
                        default   => $state,
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Felhasználó')
                    ->placeholder('rendszer'),

                Tables\Columns\TextColumn::make('context')
                    ->label('Kontextus')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('auditable_type')
                    ->label('Tábla')
                    ->options(
                        AuditLog::query()->distinct()->pluck('auditable_type', 'auditable_type')
                            ->mapWithKeys(fn ($v, $k) => [$k => class_basename($k)])
                            ->toArray()
                    ),
                Tables\Filters\SelectFilter::make('event')
                    ->label('Esemény')
                    ->options([
                        'created' => 'Létrehozva',
                        'updated' => 'Módosítva',
                        'deleted' => 'Törölve',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('')->tooltip('Részletek'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('created_at')
                ->label('Időpont')
                ->content(fn (AuditLog $record) => $record->created_at?->format('Y-m-d H:i:s')),
            Forms\Components\Placeholder::make('auditable')
                ->label('Rekord')
                ->content(fn (AuditLog $record) => class_basename($record->auditable_type).' #'.$record->auditable_id),
            Forms\Components\Placeholder::make('user')
                ->label('Felhasználó')
                ->content(fn (AuditLog $record) => $record->user?->name ?? 'rendszer'),
            Forms\Components\Placeholder::make('old_values')
                ->label('Régi értékek')
                ->content(fn (AuditLog $record) => new \Illuminate\Support\HtmlString(
                    '<pre style="white-space:pre-wrap">'.e(json_encode($record->old_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre>'
                )),
            Forms\Components\Placeholder::make('new_values')
                ->label('Új értékek')
                ->content(fn (AuditLog $record) => new \Illuminate\Support\HtmlString(
                    '<pre style="white-space:pre-wrap">'.e(json_encode($record->new_values, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre>'
                )),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
            'view'  => Pages\ViewAuditLog::route('/{record}'),
        ];
    }
}

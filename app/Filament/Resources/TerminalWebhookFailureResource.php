<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerminalWebhookFailureResource\Pages;
use App\Models\TerminalWebhookFailure;
use Filament\Forms;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\Facades\Auth;

class TerminalWebhookFailureResource extends Resource
{
    protected static ?string $model = TerminalWebhookFailure::class;

    protected static ?string $navigationIcon  = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Webhook hibák';
    protected static ?string $modelLabel      = 'Webhook hiba';
    protected static ?string $pluralLabel     = 'Webhook hibák';

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
        return false; // csak olvasható napló, a rendszer maga tölti fel
    }

    public static function canEdit($record): bool
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

                Tables\Columns\TextColumn::make('error_code')
                    ->label('Hiba')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'unauthorized'    => 'danger',
                        'unknown_card'    => 'warning',
                        'no_open_entry'   => 'warning',
                        'validation'      => 'gray',
                        'no_system_user'  => 'danger',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'unauthorized'    => 'Érvénytelen token',
                        'unknown_card'    => 'Ismeretlen kártya',
                        'no_open_entry'   => 'Nincs nyitott belépés',
                        'validation'      => 'Validációs hiba',
                        'no_system_user'  => 'Nincs rendszerfelhasználó',
                        default           => $state,
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('http_status')
                    ->label('HTTP kód')
                    ->badge()
                    ->color(fn (int $state) => $state >= 500 ? 'danger' : ($state >= 400 ? 'warning' : 'gray')),

                Tables\Columns\TextColumn::make('card_uid')
                    ->label('Kártya UID')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('direction')
                    ->label('Irány')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'in'  => 'Belépés',
                        'out' => 'Kilépés',
                        default => '—',
                    }),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP cím')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('message')
                    ->label('Üzenet')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('error_code')
                    ->label('Hiba típusa')
                    ->options([
                        'unauthorized'   => 'Érvénytelen token',
                        'unknown_card'   => 'Ismeretlen kártya',
                        'no_open_entry'  => 'Nincs nyitott belépés',
                        'validation'     => 'Validációs hiba',
                        'no_system_user' => 'Nincs rendszerfelhasználó',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('')->tooltip('Részletek'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Placeholder::make('created_at')
                ->label('Időpont')
                ->content(fn (TerminalWebhookFailure $record) => $record->created_at?->format('Y-m-d H:i:s')),
            Forms\Components\Placeholder::make('error_code')
                ->label('Hiba')
                ->content(fn (TerminalWebhookFailure $record) => $record->error_code.' (HTTP '.$record->http_status.')'),
            Forms\Components\Placeholder::make('message')
                ->label('Üzenet')
                ->content(fn (TerminalWebhookFailure $record) => $record->message ?? '—'),
            Forms\Components\Placeholder::make('ip_address')
                ->label('IP cím')
                ->content(fn (TerminalWebhookFailure $record) => $record->ip_address ?? '—'),
            Forms\Components\Placeholder::make('payload')
                ->label('Beérkezett kérés törzse (nyers)')
                ->content(fn (TerminalWebhookFailure $record) => new \Illuminate\Support\HtmlString(
                    '<pre style="white-space:pre-wrap">'.e(json_encode($record->payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)).'</pre>'
                )),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerminalWebhookFailures::route('/'),
            'view'  => Pages\ViewTerminalWebhookFailure::route('/{record}'),
        ];
    }
}

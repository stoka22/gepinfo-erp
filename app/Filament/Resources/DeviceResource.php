<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers\ChannelsRelationManager;
use App\Filament\Resources\DeviceResource\RelationManagers\DeviceFilesRelationManager;
use App\Models\Command;
use App\Models\Device;
use App\Models\Firmware;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Columns\ToggleColumn;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'Eszközök';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Általános')->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('mac_address')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('A firmware ebből (kettőspont nélkül, ESP32_/ESP8266_ előtaggal) származtatja az enrollment device_id-t.'),
                Forms\Components\TextInput::make('location'),
                Forms\Components\Toggle::make('cron_enabled')
                    ->label('')
                    ->inline(false)
                    ->extraAttributes(['title' => 'Cron ki/bekapcsolása ehhez az eszközhöz']),
            ])->columns(2)
                ->helperText('A gép-hozzárendelés csatornánként (d1-d4) történik, lásd lent.'),

            Forms\Components\Section::make('Firmware / Telemetria')->schema([
                Forms\Components\TextInput::make('platform')->label('Platform')->disabled(),
                Forms\Components\TextInput::make('fw_version')->label('FW verzió')->disabled(),
                Forms\Components\TextInput::make('ssid')->disabled(),
                Forms\Components\TextInput::make('rssi')->numeric()->disabled(),
                Forms\Components\DateTimePicker::make('last_seen_at')->disabled(),
                Forms\Components\TextInput::make('last_ip')->disabled(),
                Forms\Components\Select::make('firmware_target_version')
                    ->label('Cél firmware-verzió')
                    ->helperText('Eszközönkénti cél (nem flotta-szintű) -- csak a device.platform-mal egyező firmware-ek közül.')
                    ->options(function (?Device $record) {
                        if (! $record?->platform) {
                            return [];
                        }

                        return Firmware::query()
                            ->where('platform', $record->platform)
                            ->orderByDesc('published_at')
                            ->pluck('version', 'version');
                    })
                    ->placeholder('— nincs cél beállítva —')
                    ->afterStateHydrated(function (Forms\Components\Select $component, ?Device $record) {
                        $component->state($record?->meta['firmware_target_version'] ?? null);
                    })
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, ?Device $record) {
                        if (! $record) {
                            return;
                        }
                        $meta = $record->meta ?? [];
                        if ($state) {
                            $meta['firmware_target_version'] = $state;
                        } else {
                            unset($meta['firmware_target_version']);
                        }
                        $record->update(['meta' => $meta]);
                    }),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User')->sortable()->toggleable(),
                TextColumn::make('name')->label('Eszköz')->searchable()->sortable(),
                TextColumn::make('mac_address')->label('MAC')->copyable()->toggleable()->sortable(),
                TextColumn::make('machines.name')
                    ->label('Gépek')
                    ->badge()
                    ->separator(',')
                    ->placeholder('— nincs csatorna hozzárendelve —')
                    ->toggleable(),

                ViewColumn::make('status_ui')
                    ->label('Státusz')
                    ->view('filament.tables.columns.device-status')
                    ->alignCenter(),

                TextColumn::make('fw_version')->label('FW')->toggleable(),
                TextColumn::make('ssid')->toggleable(),
                TextColumn::make('rssi')->label('RSSI')->toggleable()->sortable(),
                TextColumn::make('last_seen_at')->label('Utolsó jel')->since()->sortable(),

                ToggleColumn::make('cron_enabled')
                    ->label('Cron')
                    ->alignCenter()
                    ->onColor('success')
                    ->offColor('gray')
                    ->extraAttributes(['title' => 'Cron ki/bekapcsolása']),
            ])
            ->poll('2s')
            ->defaultSort('last_seen_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('enableAllCron')
                    ->label('Mind bekapcsol')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn () => Device::query()->update(['cron_enabled' => true])),
                Tables\Actions\Action::make('disableAllCron')
                    ->label('Mind kikapcsol')
                    ->icon('heroicon-o-pause')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn () => Device::query()->update(['cron_enabled' => false])),
            ])
            ->actions([
                // Az OTA-frissítés mostantól a "Cél firmware-verzió" mezőn
                // (szerkesztő űrlap) keresztül megy -- a firmware a push-
                // válasz `firmware{version,url}` kulcsán keresztül kapja meg
                // a célt, nem egy egyszeri, kézzel megadott URL-es
                // parancson. A régi "ota"/"rollback" gomb (ami egy
                // tetszőleges URL-t küldött egy Command-ban) nincs is
                // bekötve a valódi firmware kontraktjába -- az
                // applyOneShotCommands() csak "reboot"/"factory_reset"
                // parancstípust ismer fel, ezért törölve.
                Tables\Actions\Action::make('reboot')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip(function ($record) {
                        if (empty($record->last_boot_at)) return false;

                        $boot = $record->last_boot_at instanceof \Carbon\Carbon
                            ? $record->last_boot_at
                            : Carbon::parse($record->last_boot_at);

                        return $boot->gte(now()->subMinutes(3));
                    })
                    ->requiresConfirmation()
                    ->action(fn (Device $record) =>
                        Command::create([
                            'device_id' => $record->id,
                            'cmd'       => 'reboot',
                            'status'    => 'pending',
                        ])
                    ),

                Tables\Actions\Action::make('factory_reset')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->iconButton()
                    ->tooltip('Factory reset')
                    ->requiresConfirmation()
                    ->action(fn (Device $record) =>
                        Command::create([
                            'device_id' => $record->id,
                            'cmd'       => 'factory_reset',
                            'status'    => 'pending',
                        ])
                    ),

                Tables\Actions\EditAction::make()->iconButton()->tooltip('Szerkesztés'),
                Tables\Actions\DeleteAction::make()->iconButton()->tooltip('Törlés'),

                Tables\Actions\Action::make('stop_commands')
                    ->label('')
                    ->icon('heroicon-o-hand-raised')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Device $record) {
                        $count = Command::where('device_id', $record->id)
                            ->where('status', 'pending')
                            ->update(['status' => 'cancelled']);

                        Notification::make()
                            ->title('Parancsok leállítva')
                            ->body("{$count} függőben lévő parancs leállítva.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    /*** <- EZ ÚJ: regisztráljuk a relation managert ***/
    public static function getRelations(): array
    {
        return [
            ChannelsRelationManager::class,
            DeviceFilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit'   => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}

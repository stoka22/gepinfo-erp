<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Filament\Resources\DeviceResource\RelationManagers\DeviceFilesRelationManager;
use App\Filament\Resources\DeviceResource\RelationManagers\FirmwaresRelationManager;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                Forms\Components\Select::make('machine_id')
                    ->relationship('machine', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('mac_address')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('location'),
                Forms\Components\Toggle::make('cron_enabled')
                    ->label('')
                    ->inline(false)
                    ->extraAttributes(['title' => 'Cron ki/bekapcsolása ehhez az eszközhöz']),
            ])->columns(2),

            Forms\Components\Section::make('Firmware / Telemetria')->schema([
                Forms\Components\TextInput::make('fw_version')->label('FW verzió')->disabled(),
                Forms\Components\TextInput::make('ssid')->disabled(),
                Forms\Components\TextInput::make('rssi')->numeric()->disabled(),
                Forms\Components\DateTimePicker::make('last_seen_at')->disabled(),
                Forms\Components\TextInput::make('last_ip')->disabled(),
                Forms\Components\TextInput::make('device_token')
                    ->disabled()
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('regen')
                            ->icon('heroicon-o-key')
                            ->label('Új token')
                            ->action(fn ($set) => $set('device_token', Str::random(48)))
                    ),
                Forms\Components\TextInput::make('ota_channel')->label('Csatorna')->placeholder('stable/beta'),
                Forms\Components\TextInput::make('rollback_url')->label('Rollback URL'),
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
                TextColumn::make('machine.name')->label('Gép')->badge()->toggleable(),

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
                Tables\Actions\Action::make('ota')
                    ->label('OTA frissítés')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('OTA frissítés')
                    ->form([
                        Forms\Components\Select::make('firmware_id')
                            ->label('Válassz firmware-t')
                            ->options(
                                \App\Models\Firmware::query()
                                    ->orderByDesc('published_at')
                                    ->orderByDesc('id')
                                    ->get()
                                    ->mapWithKeys(fn ($fw) => [
                                        $fw->id => trim(
                                            collect([
                                                $fw->version,
                                                $fw->hardware_code ? "({$fw->hardware_code})" : null,
                                                $fw->published_at ? $fw->published_at->format('Y-m-d') : null,
                                            ])->filter()->implode(' ')
                                        ),
                                    ])
                                    ->all()
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data, Device $record) {
                        $fw  = Firmware::findOrFail($data['firmware_id']);

                        // abszolút URL az aktuális hosttal (nem ragad localhost-ra)
                        $url = url(Storage::url($fw->file_path));

                        Command::create([
                            'device_id' => $record->id,
                            'cmd'       => 'ota',
                            'args'      => ['url' => $url],
                            'status'    => 'pending',
                        ]);

                        Notification::make()
                            ->title('OTA parancs kiadva')
                            ->body("FW: {$fw->version}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('rollback')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->iconButton()
                    ->tooltip('Visszatérés')
                    ->requiresConfirmation()
                    ->action(function (Device $record) {
                        if (!$record->rollback_url) {
                            Notification::make()->title('Nincs rollback URL')->danger()->send();
                            return;
                        }
                        Command::create([
                            'device_id' => $record->id,
                            'cmd'       => 'ota',
                            'args'      => ['url' => $record->rollback_url],
                            'status'    => 'pending',
                        ]);
                        Notification::make()->title('Rollback parancs kiadva')->success()->send();
                    }),

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

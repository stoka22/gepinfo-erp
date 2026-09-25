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
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
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
                ->description('A gép-hozzárendelés csatornánként (d1-d4) történik, lásd lent.'),

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

            Forms\Components\Section::make('WiFi hálózatok')
                ->description('Priorizált SSID/jelszó lista, amit a firmware a beégetett hotspot ELŐTT próbál. A sorrend számít -- a lista tetején lévőt próbálja először. Üresen hagyott jelszó a meglévőt megtartja (ugyanahhoz az SSID-hez).')
                ->hidden(fn (?Device $record) => ! $record)
                ->schema([
                    Forms\Components\Repeater::make('wifi_networks_input')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('ssid')
                                ->label('SSID')
                                ->required()
                                ->maxLength(64),
                            Forms\Components\TextInput::make('password')
                                ->label('Jelszó')
                                ->password()
                                ->revealable()
                                ->maxLength(64)
                                ->placeholder('•••••••• (üresen hagyva: változatlan)'),
                        ])
                        ->columns(2)
                        ->reorderable()
                        ->reorderableWithButtons()
                        ->addActionLabel('Új hálózat hozzáadása')
                        ->dehydrated()
                        ->afterStateHydrated(function (Forms\Components\Repeater $component, ?Device $record) {
                            $component->state(
                                collect($record?->meta['wifi_networks'] ?? [])
                                    ->map(fn (array $n) => ['ssid' => $n['ssid'], 'password' => ''])
                                    ->all()
                            );
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Kompakt, több soros elrendezés: minden bejegyzés itt egy
                // ÖNÁLLÓ táblázat-oszlop (nem egy közös Split-be csomagolva),
                // hogy a sorok normál táblaként, egységesen igazodjanak
                // egymás alá -- egy közös Split flex-konténerben a cellák
                // szélessége soronként eltérően alakult volna a tartalom
                // hossza szerint, ami "összevissza" (nem oszlopba igazodó)
                // hatást keltett. Egy-egy Stack-en belül 2 összetartozó mező
                // kerül egymás alá, hogy a teljes sor szélessége csökkenjen.
                Stack::make([
                    TextColumn::make('name')
                        ->label('Eszköz')
                        ->weight(FontWeight::Bold)
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('mac_address')
                        ->label('MAC')
                        ->copyable()
                        ->color('gray')
                        ->size(TextColumnSize::Small)
                        ->sortable()
                        ->toggleable(),
                ])->space(1),

                Stack::make([
                    TextColumn::make('user.name')
                        ->label('User')
                        ->color('gray')
                        ->size(TextColumnSize::Small)
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('machines.name')
                        ->label('Gépek')
                        ->badge()
                        ->separator(',')
                        ->placeholder('— nincs csatorna hozzárendelve —')
                        ->toggleable(),
                ])->space(1),

                // Önálló, egységesen igazodó oszlop -- zöld pipa (online) /
                // piros x (offline). Inline style-lal színezve, mert a
                // Filament admin panel LEFORDÍTOTT CSS-e (vendor/filament/
                // filament/dist/theme.css) csak a saját palettájának
                // ténylegesen használt osztályait tartalmazza (pl.
                // text-danger-500, text-gray-500, text-primary-500) -- a
                // blade-ben korábban használt tetszőleges "!text-green-500"/
                // "!text-red-500" Tailwind-osztályoknak ebben a fájlban
                // SOSEM volt CSS-szabálya, ezért nem is látszottak színesnek.
                ViewColumn::make('status_ui')
                    ->label('Státusz')
                    ->view('filament.tables.columns.device-status')
                    ->alignCenter(),

                Stack::make([
                    TextColumn::make('ssid')
                        ->label('SSID')
                        ->color('gray')
                        ->size(TextColumnSize::Small)
                        ->toggleable(),
                    TextColumn::make('rssi')
                        ->label('RSSI')
                        ->color('gray')
                        ->size(TextColumnSize::Small)
                        ->sortable()
                        ->toggleable(),
                ])->space(1),

                TextColumn::make('fw_version')
                    ->label('FW')
                    ->color('gray')
                    ->size(TextColumnSize::Small)
                    ->toggleable(),

                TextColumn::make('last_seen_at')
                    ->label('Utolsó jel')
                    ->formatStateUsing(function (?Carbon $state): string {
                        if (! $state) {
                            return '—';
                        }

                        $seconds = $state->diffInSeconds(now());
                        if ($seconds < 60) {
                            return $seconds.'s';
                        }

                        $minutes = intdiv($seconds, 60);
                        if ($minutes < 60) {
                            return $minutes.'m';
                        }

                        $hours = intdiv($minutes, 60);
                        if ($hours < 24) {
                            return $hours.':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
                        }

                        return intdiv($hours, 24).'d';
                    })
                    ->sortable(),

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

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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
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
                Forms\Components\Toggle::make('simulate_test_pulses_input')
                    ->label('Teszt-impulzus szimulátor')
                    ->helperText('Firmware-oldali kapcsoló: bekapcsolva az eszköz saját magának szimulál impulzusokat csatornánként (valós szenzor nélkül is folyamatosan nő a számláló). Az eszköz a következő push-nál (nem csak újraindításkor) alkalmazza.')
                    ->afterStateHydrated(function (Forms\Components\Toggle $component, ?Device $record) {
                        $component->state((bool) ($record?->meta['simulate_test_pulses'] ?? false));
                    })
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(function (bool $state, ?Device $record) {
                        if (! $record) {
                            return;
                        }
                        $meta = $record->meta ?? [];
                        $meta['simulate_test_pulses'] = $state;
                        $record->update(['meta' => $meta]);
                    }),
            ])->columns(3),

            Forms\Components\Section::make('WiFi hálózatok')
                ->description('Priorizált SSID/jelszó lista, amit a firmware a beégetett hotspot ELŐTT próbál. A sorrend számít -- a lista tetején lévőt próbálja először. Üresen hagyott jelszó a meglévőt megtartja (ugyanahhoz az SSID-hez).')
                ->hidden(fn (?Device $record) => ! $record)
                ->schema([
                    Forms\Components\Placeholder::make('wifi_scan_empty')
                        ->label('Legutóbb észlelt hálózatok (csatlakozáskor mérve, RSSI szerint)')
                        ->content('Nincs még mentett keresési eredmény.')
                        ->visible(fn (?Device $record) => empty($record?->meta['live']['wifi_scan'])),
                    // Kattintható gombok, egyenként a legutóbbi keresés max
                    // 5 SSID-jéhez -- a régi verzió csak egy statikus, NEM
                    // kattintható badge-listát mutatott (Placeholder), ezért
                    // a felhasználó nem tudott a beolvasott hálózatok közül
                    // választani (a screenshotján a böngésző SAJÁT
                    // jelszó-kitöltő ablaka jelent meg az üres SSID mezőben,
                    // azzal semmi kapcsolatuk). A form() statikus metódus
                    // nem kapja meg közvetlenül a $record-ot, ezért fix 5
                    // "slot" Action készül, mindegyik saját closure-ral
                    // (label/visible/action) dönti el futásidőben, van-e
                    // hozzá tartozó scan-bejegyzés -- Set/Get-tel közvetlenül
                    // a wifi_networks_input Repeater állapotába ír, ugyanúgy
                    // mint az Energy "+ Hozzáadás" gombja (ott vanilla
                    // JS-sel, itt a Filament saját, form-natív
                    // Action-mechanizmusával).
                    Forms\Components\Actions::make(
                        collect(range(0, 4))->map(fn (int $i) => Forms\Components\Actions\Action::make("add_scan_{$i}")
                            ->label(function (?Device $record) use ($i) {
                                $net = $record?->meta['live']['wifi_scan'][$i] ?? null;
                                return $net ? ('+ '.$net['ssid'].' ('.($net['rssi'] ?? '-').' dBm)') : '';
                            })
                            ->size('sm')
                            ->color('gray')
                            ->visible(fn (?Device $record) => ! empty($record?->meta['live']['wifi_scan'][$i]['ssid']))
                            ->action(function (Set $set, Get $get, ?Device $record) use ($i) {
                                $ssid = $record?->meta['live']['wifi_scan'][$i]['ssid'] ?? null;
                                if (! $ssid) {
                                    return;
                                }
                                $current = collect($get('wifi_networks_input') ?? []);
                                if ($current->contains(fn (array $row) => ($row['ssid'] ?? null) === $ssid)) {
                                    return;
                                }
                                $set('wifi_networks_input', $current->push(['ssid' => $ssid, 'password' => ''])->values()->all());
                            }))->all()
                    )
                        ->visible(fn (?Device $record) => ! empty($record?->meta['live']['wifi_scan'])),
                    Forms\Components\Repeater::make('wifi_networks_input')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('ssid')
                                ->label('SSID')
                                ->required()
                                ->maxLength(64)
                                ->autocomplete(false),
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
                // Sima, egysoros Filament-oszlopok -- egyéni Stack/Split
                // elrendezéssel próbálkoztunk korábban, de az élesben rosszul
                // nézett ki (a sorok nem igazodtak rendesen, a Státusz-ikon
                // elcsúszva jelent meg). A kompaktságot most a natív,
                // jól bevált módon oldjuk meg: a másodlagos oszlopok
                // (MAC, User, SSID, RSSI, FW) alapból el vannak rejtve
                // (->toggleable(isToggledHiddenByDefault: true)), a jobb
                // felső oszlopválasztó gombbal bármikor visszakapcsolhatók.
                TextColumn::make('name')
                    ->label('Eszköz')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('mac_address')
                    ->label('MAC')
                    ->copyable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('User')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('machines.name')
                    ->label('Gépek')
                    ->badge()
                    ->separator(',')
                    ->placeholder('— nincs csatorna hozzárendelve —')
                    ->toggleable(),

                // Zöld pipa (online) / piros x (offline). Inline style-lal
                // színezve, mert a Filament admin panel LEFORDÍTOTT CSS-e
                // (vendor/filament/filament/dist/theme.css) csak a saját
                // palettája ténylegesen használt osztályait tartalmazza
                // (pl. text-danger-500, text-gray-500, text-primary-500) --
                // egy tetszőleges "!text-green-500"/"!text-red-500"
                // Tailwind-osztálynak ebben a fájlban SOSEM volt
                // CSS-szabálya, ezért nem is látszott színesnek.
                ViewColumn::make('status_ui')
                    ->label('Státusz')
                    ->view('filament.tables.columns.device-status')
                    ->alignCenter(),

                TextColumn::make('ssid')
                    ->label('SSID')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('rssi')
                    ->label('RSSI')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fw_version')
                    ->label('FW')
                    ->toggleable(isToggledHiddenByDefault: true),

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

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirmwareResource\Pages;
use App\Models\Firmware;
use Filament\Forms\Components\{Select, TextInput, Textarea, Toggle, DateTimePicker, FileUpload, Hidden};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class FirmwareResource extends Resource
{
    protected static ?string $model = Firmware::class;
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Eszközök';
    protected static ?string $navigationLabel = 'Firmware kiadások';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('version')->label('Verzió')->searchable()->sortable()->badge(),
                Tables\Columns\TextColumn::make('platform')->label('Platform')->badge()->sortable(),
                Tables\Columns\TextColumn::make('build')->label('Build')->sortable(),
                Tables\Columns\TextColumn::make('device.name')->label('Eszköz'),
                Tables\Columns\TextColumn::make('hardware_code')->label('Hardverkód')->toggleable(),
                Tables\Columns\TextColumn::make('md5')->label('MD5')->copyable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('forced')->label('Kötelező')->boolean(),
                Tables\Columns\TextColumn::make('published_at')->label('Kiadás')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('')->tooltip('Megtekintés'),
                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
                Tables\Actions\Action::make('download')
                    ->label('')
                    ->tooltip('Letöltés')
                    ->icon('heroicon-o-arrow-down-tray')
                    // A fájl a privát 'local' diskről jön (nincs nyilvános
                    // URL-je), ezért egy admin-hitelesített stream-letöltés,
                    // nem Storage::url().
                    ->action(fn (Firmware $r) => Storage::disk('local')
                        ->download($r->file_path, $r->version.'.bin')),
            ])
            ->defaultSort('published_at','desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFirmwares::route('/'),
            'create' => Pages\CreateFirmware::route('/create'),
            'edit'   => Pages\EditFirmware::route('/{record}/edit'),
            'view'   => Pages\ViewFirmware::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Firmware::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('device_id')
                ->label('Eszköz (opcionális)')
                ->relationship('device', 'name')
                ->searchable()
                ->placeholder('Univerzális / hardverkód alapján'),

            Select::make('platform')
                ->label('Platform')
                ->options(['esp32' => 'ESP32', 'esp8266' => 'ESP8266'])
                ->required()
                ->default('esp32')
                ->helperText('A push-válasz csak a device.platform-mal EGYEZŐ firmware-t ajánlja fel -- ESP8266-os binárist sosem kap ESP32-s eszköz és fordítva.'),

            TextInput::make('hardware_code')->label('Hardverkód')->placeholder('pl. ESP32-WROOM-32E'),

            TextInput::make('version')->required()->label('Verzió')->placeholder('1.2.3'),

            TextInput::make('build')->numeric()->minValue(1)->default(1),

            FileUpload::make('file_path')
                ->label('Firmware fájl')
                ->disk('local')
                ->directory('firmware')
                ->required()
                ->preserveFilenames()
                ->openable()
                ->downloadable()
                ->acceptedFileTypes(['application/octet-stream','.bin','.uf2','.zip'])
                ->maxSize(100*1024)
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if (!$state) return;
                    $file = is_array($state) ? ($state[0] ?? null) : $state;
                    if (!$file) return;

                    if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $originalName = $file->getClientOriginalName();
                        $basename     = pathinfo($originalName, PATHINFO_FILENAME);

                        // meta
                        $set('mime_type', $file->getMimeType());
                        $set('file_size', $file->getSize());
                        $set('sha256', hash_file('sha256', $file->getRealPath()));

                        if (preg_match('/(?:^|[_\-])v?(\d+\.\d+(?:\.\d+)?)/i', $basename, $m)) {
                            $set('version', $m[1]);
                        }
                        if (preg_match('/(?:^|[_\-])b(?:uild)?\s*(\d{1,6})/i', $basename, $m)) {
                            $set('build', (int) $m[1]);
                        }
                        if (preg_match('/(ESP32(?:-WROOM-\w+)?|ESP8266|STM32\w+|RP2040)/i', $basename, $m)) {
                            $set('hardware_code', strtoupper($m[1]));
                            if (stripos($m[1], 'ESP32') === 0) {
                                $set('platform', 'esp32');
                            } elseif (stripos($m[1], 'ESP8266') === 0) {
                                $set('platform', 'esp8266');
                            }
                        }
                        $mtime = @filemtime($file->getRealPath());
                        if ($mtime) {
                            $set('published_at', Carbon::createFromTimestamp($mtime));
                        }
                    }
                }),

            Toggle::make('forced')->label('Kötelező frissítés'),

            DateTimePicker::make('published_at')->label('Kiadva ekkor')->seconds(false),

            Textarea::make('notes')->label('Megjegyzés')->rows(3),

            // rejtett meta mezők
            Hidden::make('file_size'),
            Hidden::make('mime_type'),
            Hidden::make('sha256'),
        ])->columns(2);
    }
}

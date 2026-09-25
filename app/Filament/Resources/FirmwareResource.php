<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FirmwareResource\Pages;
use App\Models\Firmware;
use Filament\Forms\Components\{Select, TextInput, Textarea, Toggle, DateTimePicker, Hidden};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

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

            // A firmware-fájl (.bin) feltöltése/cseréje SZÁNDÉKOSAN nincs
            // itt -- a Filament FileUpload komponens a /livewire/upload-file
            // végpontot használja, amit élesben egy WAF-szabály blokkol
            // bináris tartalomra (lásd FirmwareUploadController
            // doc-kommentjét). Új firmware feltöltése ezért egy külön, sima
            // <form>-alapú oldalon történik (CreateFirmware egyedi nézete);
            // szerkesztéskor a bináris NEM cserélhető (ahogy az Energy
            // projekt mintája is csak létrehozást/törlést ismer, cserét
            // nem) -- egy új verzióhoz új firmware-rekordot kell feltölteni.

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

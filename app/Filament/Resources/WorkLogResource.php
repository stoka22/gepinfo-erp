<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Employee;
use App\Models\WorkLog;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\WorkLogResource\Pages\EditWorkLog;
use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Filament\Resources\WorkLogResource\Pages\CreateWorkLog;

class WorkLogResource extends Resource
{
    protected static ?string $model = WorkLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
    protected static ?string $navigationGroup = 'Importálás';
    protected static ?string $navigationLabel = 'Munkaidő napló';
    protected static ?string $pluralLabel = 'Munkaidő naplók';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nev')
                    ->label('Név')
                    ->required(),

                Forms\Components\Select::make('employee_id')
                    ->label('Dolgozó')
                    ->options(\App\Models\Employee::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->placeholder('Válassz dolgozót')
                    ->helperText('Ha nincs automatikus egyezés, kézzel válaszd ki.')
                    ->required(false),
                Forms\Components\Toggle::make('is_archived')
                    ->label('Archivált')
                    ->helperText('Ha ez a sor már nem aktív dolgozóhoz tartozik.')
                    ->default(false),


                Forms\Components\TextInput::make('munkakor')
                    ->label('Munkakör')
                    ->required(),

                Forms\Components\TextInput::make('helyiseg')
                    ->label('Helyiség'),

                Forms\Components\TextInput::make('belepesi_pont')
                    ->label('Belépési pont'),

                Forms\Components\DateTimePicker::make('kezdes')
                    ->label('Kezdés')
                    ->required(),

                Forms\Components\TextInput::make('kilepesi_pont')
                    ->label('Kilépési pont'),

                Forms\Components\DateTimePicker::make('vege')
                    ->label('Vége')
                    ->required(),

                Forms\Components\TextInput::make('ido')
                    ->label('Idő')
                    ->hint('Pl. 3:57'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nev')->label('Név')->searchable()->sortable()->toggleable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Dolgozó')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Archivált')
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('munkakor')->label('Munkakör')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('helyiseg')->label('Helyiség')->searchable()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('belepesi_pont')->label('Belépési pont')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kezdes')->label('Kezdés')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('kilepesi_pont')->label('Kilépési pont')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('vege')->label('Vége')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('ido')->label('Idő')->sortable()->toggleable()
                    ->formatStateUsing(fn (?string $state) => \App\Imports\WorkLogsImport::formatIdo($state)),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('munkakor')
                    ->label('Munkakör')
                    ->options(
                        WorkLog::query()->distinct()->pluck('munkakor', 'munkakor')->toArray()
                    ),
                Tables\Filters\SelectFilter::make('is_archived')
                    ->label('Arhivált')
                    ->options(
                        WorkLog::query()
                            ->distinct()
                            ->pluck('is_archived', 'is_archived')
                            ->toArray()
                    )
                    ->options([
                        0 => 'Nem',
                        1 => 'Igen',
                    ])
                    ->default(0),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('')->tooltip('Szerkesztés'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Törlés'),
            ])
            ->bulkActions([


                Tables\Actions\BulkAction::make('archive_selected')
                    ->label('Kiválasztott archiválása (és azonos nevűek)')
                    ->icon('heroicon-o-archive-box')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            \App\Models\WorkLog::where('nev', $record->nev)
                                ->update(['is_archived' => true]);
                        }
                    }),

                Tables\Actions\BulkAction::make('link_selected')
                    ->label('Összekapcsolás dolgozóval')
                    ->icon('heroicon-o-link')
                    ->form([
                        Forms\Components\Select::make('employee_id')
                            ->label('Dolgozó')
                            ->options(\App\Models\Employee::pluck('name', 'id')->toArray())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function ($records, $data) {
                        // A puszta employee_id beállítása önmagában NEM elég: a jelenléti ív és a
                        // túlóra-keret kizárólag a time_entries táblát olvassa, úgyhogy minden
                        // most összekapcsolt sorhoz létre kell hozni a hozzá tartozó jelenlét-
                        // bejegyzést is — ugyanazzal a logikával, mint amit maga az import használ,
                        // különben a sor a munkaidő naplóban látszik, de sehol máshol.
                        $import = new \App\Imports\WorkLogsImport;
                        foreach ($records as $record) {
                            \App\Models\WorkLog::where('nev', $record->nev)
                                ->update(['employee_id' => $data['employee_id']]);

                            \App\Models\WorkLog::where('nev', $record->nev)
                                ->where('employee_id', $data['employee_id'])
                                ->get()
                                ->each(function (\App\Models\WorkLog $log) use ($import) {
                                    $import->createPresenceEntry([
                                        'kezdes'      => $log->kezdes?->format('Y-m-d H:i:s'),
                                        'vege'        => $log->vege?->format('Y-m-d H:i:s'),
                                        'helyiseg'    => $log->helyiseg,
                                        'employee_id' => $log->employee_id,
                                    ]);
                                });
                        }
                    }),


                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('archive')
                    ->label('Archiválás')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()

                    ->action(function (WorkLog $record) {
                        \App\Models\WorkLog::where('nev', $record->nev)
                            ->update(['is_archived' => true]);
                    }),

                Tables\Actions\Action::make('import')
                    ->label('Importálás XLS-ből')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->steps([
                        Forms\Components\Wizard\Step::make('Fájl feltöltése')
                            ->schema([
                                Forms\Components\FileUpload::make('file')
                                    ->label('XLS fájl')
                                    ->disk('public')
                                    ->directory('imports')
                                    ->live()
                                    ->required(),
                            ]),
                        Forms\Components\Wizard\Step::make('Dolgozó párosítás')
                            ->schema(fn (Get $get) => static::unmatchedNameFields($get('file'))),
                        Forms\Components\Wizard\Step::make('Ellenőrzés')
                            ->schema(fn (Get $get) => static::previewFields($get)),
                    ])
                    ->action(function (array $data) {
                        $fullPath = static::resolveUploadedFilePath($data['file'] ?? null);

                        if ($fullPath === null) {
                            Notification::make()
                                ->title('A fájl nem található, próbáld újra feltölteni.')
                                ->danger()
                                ->send();
                            return;
                        }

                        $import = new \App\Imports\WorkLogsImport;

                        $assignments = [];
                        foreach ($import->unmatchedNames($fullPath) as $nev) {
                            $key = static::unmatchedNameFieldKey($nev);
                            if (! empty($data[$key])) {
                                $assignments[$nev] = (int) $data[$key];
                            }
                        }

                        $count = $import->import($fullPath, $assignments);

                        Notification::make()
                            ->title("Import kész ({$count} sor)")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('truncate')
                    ->label('Tábla törlése')
                    ->icon('heroicon-o-trash')
                    ->requiresConfirmation()
                    ->action(function () {
                        \App\Models\WorkLog::truncate();
                    }),



            ])
        ;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkLogs::route('/'),
            'create' => CreateWorkLog::route('/create'),
            'edit' => EditWorkLog::route('/{record}/edit'),
        ];
    }

    /**
     * A varázsló lépései között a FileUpload állapota még nem a végleges, lemezre
     * mentett elérési út — amíg az egész űrlap be nem lett küldve (az action() előtt
     * dehidratálva), egy Livewire ideiglenes feltöltési objektum (TemporaryUploadedFile).
     * Ez mindkét alakot kezeli, és a fájl tényleges, éppen olvasható elérési útját adja.
     */
    protected static function resolveUploadedFilePath(mixed $uploadedFile): ?string
    {
        if (is_array($uploadedFile)) {
            $uploadedFile = reset($uploadedFile) ?: null;
        }

        if ($uploadedFile instanceof \Illuminate\Http\UploadedFile) {
            $real = $uploadedFile->getRealPath();
            return ($real && is_file($real)) ? $real : null;
        }

        if (is_string($uploadedFile) && $uploadedFile !== '') {
            $path = Storage::disk('public')->path($uploadedFile);
            return is_file($path) ? $path : null;
        }

        return null;
    }

    /** A "Dolgozó párosítás" varázsló-lépés mezői: minden automatikusan nem egyező névhez egy Select. */
    protected static function unmatchedNameFields(mixed $uploadedFile): array
    {
        if (empty($uploadedFile)) {
            return [
                Forms\Components\Placeholder::make('info')
                    ->label('')
                    ->content('Előbb tölts fel egy fájlt az előző lépésben.'),
            ];
        }

        $fullPath = static::resolveUploadedFilePath($uploadedFile);

        if ($fullPath === null) {
            return [
                Forms\Components\Placeholder::make('info')
                    ->label('')
                    ->content('A fájl nem található, próbáld újra feltölteni.'),
            ];
        }

        $unmatched = (new \App\Imports\WorkLogsImport)->unmatchedNames($fullPath);

        if (empty($unmatched)) {
            return [
                Forms\Components\Placeholder::make('info')
                    ->label('')
                    ->content('Minden névhez sikerült automatikusan dolgozót azonosítani.'),
            ];
        }

        $employeeOptions = Employee::orderBy('name')->pluck('name', 'id');

        $fields = [
            Forms\Components\Placeholder::make('info')
                ->label('')
                ->content('A következő nevekhez nem található automatikusan egyező dolgozó — válaszd ki kézzel, vagy hagyd üresen, ha később rendeled hozzá a listában.'),
        ];

        foreach ($unmatched as $nev) {
            $fields[] = Forms\Components\Select::make(static::unmatchedNameFieldKey($nev))
                ->label($nev)
                ->options($employeeOptions)
                ->searchable()
                ->placeholder('— nincs hozzárendelve —');
        }

        return $fields;
    }

    /** Egyedi, biztonságos form-mezőnév egy tetszőleges (importból származó) névhez. */
    protected static function unmatchedNameFieldKey(string $nev): string
    {
        return 'assign_' . md5($nev);
    }

    /**
     * Az "Ellenőrzés" varázsló-lépés tartalma: a ténylegesen importálásra kerülő sorok
     * összesítője, a dolgozó nélkül maradó (problémás) sorok kiemelésével — az import
     * csak ennek megtekintése/jóváhagyása után indul el (a végső "Importálás" gombbal).
     */
    protected static function previewFields(Get $get): array
    {
        $fullPath = static::resolveUploadedFilePath($get('file'));

        if ($fullPath === null) {
            return [
                Forms\Components\Placeholder::make('info')
                    ->label('')
                    ->content('A fájl nem található, lépj vissza és töltsd fel újra.'),
            ];
        }

        $import = new \App\Imports\WorkLogsImport;

        $assignments = [];
        foreach ($import->unmatchedNames($fullPath) as $nev) {
            $value = $get(static::unmatchedNameFieldKey($nev));
            if (! empty($value)) {
                $assignments[$nev] = (int) $value;
            }
        }

        $rows = $import->resolveRows($fullPath, $assignments);
        $problemRows = array_values(array_filter($rows, fn (array $row) => $row['employee_id'] === null));

        return [
            Forms\Components\View::make('filament.forms.worklog-import-preview')
                ->viewData([
                    'total' => count($rows),
                    'okCount' => count($rows) - count($problemRows),
                    'problemRows' => $problemRows,
                ]),
        ];
    }



    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['employee_id']) && !empty($data['nev'])) {
            \App\Models\WorkLog::where('nev', $data['nev'])
                ->update(['employee_id' => $data['employee_id']]);
        }
        return $data;
    }
}

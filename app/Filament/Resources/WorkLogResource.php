<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\WorkLog;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
//use Filament\Notifications\Collection;
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\WorkLogResource\Pages\EditWorkLog;
use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Filament\Resources\WorkLogResource\Pages\CreateWorkLog;

class WorkLogResource extends Resource
{
    protected static ?string $model = WorkLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';
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
                Tables\Columns\TextColumn::make('nev')->label('Név')->searchable()->sortable(),

                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Dolgozó')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_archived')
                    ->label('Archivált')
                    ->boolean(),

                Tables\Columns\TextColumn::make('munkakor')->label('Munkakör')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('helyiseg')->label('Helyiség')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('belepesi_pont')->label('Belépési pont')->sortable(),
                Tables\Columns\TextColumn::make('kezdes')->label('Kezdés')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('kilepesi_pont')->label('Kilépési pont')->sortable(),
                Tables\Columns\TextColumn::make('vege')->label('Vége')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('ido')->label('Idő')->sortable(),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
                        foreach ($records as $record) {
                            \App\Models\WorkLog::where('nev', $record->nev)
                                ->update(['employee_id' => $data['employee_id']]);
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
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('XLS fájl')
                            ->disk('public')
                            ->directory('imports')
                            ->required(),

                    ])
                    ->action(function (array $data) {
                        $path = $data['file']; // pl. "imports/abc.xlsx"
                        $fullPath = Storage::disk('public')->path($path);

                        (new \App\Imports\WorkLogsImport)->import($fullPath);
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



    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['employee_id']) && !empty($data['nev'])) {
            \App\Models\WorkLog::where('nev', $data['nev'])
                ->update(['employee_id' => $data['employee_id']]);
        }
        return $data;
    }
}

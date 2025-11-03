<?php

namespace App\Filament\Resources\CardImportResource\Pages;

use App\Filament\Resources\CardImportResource;
use App\Models\CardImport;
use App\Models\Company;
use App\Services\CardApplyService;
use App\Services\CardImportService;
use Filament\Actions;                // <<< EZ KELL (nem Tables\Actions)
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;


class ListCardImports extends ListRecords
{
    protected static string $resource = CardImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_import')
                ->label('Új import (fájl feltöltés)')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalWidth('2xl')
                ->form([
                    Forms\Components\Section::make('Fájl')->schema([
                       Forms\Components\FileUpload::make('file')
                            ->label('RFID/UID export')
                            ->directory('imports')
                            ->disk('local')
                            ->preserveFilenames()
                            ->maxSize(15_000)                     // ~15 MB
                            // Frontenden is kiterjesztések:
                            //->acceptedFileTypes(['.csv', '.xls', '.xlsx'])
                            // SZERVER oldali validáció KITERJESZTÉSRE — NEM mimetypes!
                            //->rules(['file', 'mimes:csv,xls,xlsx,txt'])
                            // barátságos hibaüzenet:
                            ->validationMessages([
                                'mimes' => 'Csak CSV / XLS / XLSX fájl tölthető fel.',
                            ])
                            ->required()
                            ->helperText('CSV / XLS / XLSX – fejléc: név (opcionális), kártya/UID (kötelező), cég (opcionális).')
                    ]),
                    Forms\Components\Section::make('Szűkítés – csak aktív dolgozók között')->schema([
                        Forms\Components\Select::make('limit_company_group_id')
                            ->label('Cégcsoport (opcionális)')
                            ->options(function () {
                                if (class_exists(\App\Models\CompanyGroup::class)) {
                                    return \App\Models\CompanyGroup::query()
                                        ->orderBy('name')->pluck('name','id')->toArray();
                                }
                                return Company::query()
                                    ->whereNotNull('company_group_id')
                                    ->selectRaw('company_group_id as id, MIN(name) as name')
                                    ->groupBy('company_group_id')
                                    ->orderBy('name')
                                    ->pluck('name','id')->toArray();
                            })
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('limit_company_id', null))
                            ->placeholder('— nincs —'),

                        Forms\Components\Select::make('limit_company_id')
                            ->label('Cég (opcionális)')
                            ->options(fn (Get $get) => Company::query()
                                ->when($get('limit_company_group_id'), fn ($q,$gid) => $q->where('company_group_id',$gid))
                                ->orderBy('name')->pluck('name','id')->toArray())
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('limit_company_group_id', null))
                            ->placeholder('— nincs —'),
                    ])->columns(2),
                ])
                //->saveUploadedFiles()
                ->action(function (array $data) {
                    $f = $data['file'] ?? null;

                    // 1) Ideiglenes fájl → mentsük a local diszkre 'imports' alá
                    $storedPath = null;
                    $abs = null;

                    if ($f instanceof TemporaryUploadedFile) {
                        $orig = $f->getClientOriginalName() ?: ('upload_' . time());
                        // minimális tisztítás
                        $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '_', $orig);
                        // ha nincs kiterjesztés, hagyjuk, a storeAs úgyis kezelni fogja
                        $storedPath = $f->storeAs('imports', $safe, 'local'); // pl. "imports/export_person_...xls"
                        $abs = Storage::disk('local')->path($storedPath);
                    } elseif (is_string($f) && $f !== '') {
                        // 2) Ha már string útvonalat kaptunk (ritkább eset)
                        $storedPath = ltrim($f, '/');
                        $abs = Storage::disk('local')->path($storedPath);
                    }

                    if (! $abs || ! file_exists($abs)) {
                        Notification::make()->title('A feltöltött fájl nem található.')->danger()->send();
                        return;
                    }

                    $limitCompanyId      = $data['limit_company_id'] ?? null;
                    $limitCompanyGroupId = $data['limit_company_group_id'] ?? null;

                    try {
                        $import = app(\App\Services\CardImportService::class)->import(
                            $abs,
                            $limitCompanyId ? (int) $limitCompanyId : null,
                            $limitCompanyGroupId ? (int) $limitCompanyGroupId : null
                        );
                        Notification::make()->title("Import kész (ID: {$import->id})")->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Import hiba')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                        return;
                    }

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\Action::make('purge_staging')
                ->label('Staging ürítése (card_imports + card_import_rows)')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    DB::transaction(function () {
                        if (Schema::hasTable('card_import_rows')) {
                            DB::table('card_import_rows')->delete();
                        }
                        if (Schema::hasTable('card_imports')) {
                            DB::table('card_imports')->delete();
                        }
                    });
                    Notification::make()
                        ->title('Staging táblák ürítve.')
                        ->success()
                        ->send();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            Actions\Action::make('apply_latest')
                ->label('Legutóbbi import élesítése')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->action(function () {
                    $latest = CardImport::query()->latest('id')->first();
                    if (! $latest) {
                        Notification::make()
                            ->title('Nincs elérhető import.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $svc = app(CardApplyService::class);
                    $res = $svc->apply($latest->id);

                    $msg = sprintf(
                        'Létrehozva: %d • Újraosztva: %d • Már kapcsolva: %d • Duplikát: %d • Inaktív cél: %d • Kihagyva: %d',
                        $res['created'] ?? 0,
                        $res['reassigned'] ?? 0,
                        $res['already_linked'] ?? 0,
                        $res['duplicates'] ?? 0,
                        $res['inactive_target'] ?? 0,
                        $res['skipped'] ?? 0,
                    );

                   // Filament::notify('success', "Élesítés kész. {$msg}");
                    Notification::make()
                            ->title( "Élesítés kész. {$msg}")
                            ->warning()
                            ->send();
                    $this->redirect(static::getResource()::getUrl('index'));
                }),
        ];
    }
}

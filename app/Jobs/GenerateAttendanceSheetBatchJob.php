<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\User;
use App\Services\AttendanceSheetService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A tömeges jelenléti ív PDF-generálás sok (akár ~60) dolgozónál percekig is eltarthat
 * (Dompdf renderelés) — háttér-jobként fut, NEM a Filament bulk action szinkron HTTP
 * kérésén belül, mert élesben mérve 10 dolgozó fölött már a webszerver/PHP időtúllépése
 * (nginx fastcgi_read_timeout / PHP execution time) hibára futtatta a kérést, mielőtt a
 * PDF elkészült volna. A kész fájlról adatbázis-értesítést kap a kérő admin, letöltési
 * linkkel — nem kell a böngészőnek a generálás végéig nyitva várnia a kérést.
 */
class GenerateAttendanceSheetBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    // Csak Linux-on (pcntl) érvényesített kényszer-limit a queue worker felől; Windows
    // queue:work alatt nincs pcntl, tehát ez itt nem korlátozza a futásidőt ténylegesen —
    // de Linux-ra költözés esetén se szakítsa félbe idő előtt egy nagy köteget.
    public int $timeout = 1800;

    /** A generált PDF-eket ennyi napig tartjuk meg letöltésre, utána a következő futás törli. */
    private const RETENTION_DAYS = 7;

    /**
     * @param  int[]  $employeeIds
     * @param  string[]  $months  "01".."12"
     */
    public function __construct(
        private readonly array $employeeIds,
        private readonly int $year,
        private readonly array $months,
        private readonly string $view,
        private readonly string $filenamePrefix,
        private readonly string $label,
        private readonly int $requestedByUserId,
    ) {
    }

    public function handle(AttendanceSheetService $service): void
    {
        $this->cleanupOldFiles();

        // Friss lekérdezés a SoftDeletes globális scope-jával — a törölt dolgozók
        // automatikusan kimaradnak, nem kell külön szűrni, mint a bulk action-ben.
        $employees = Employee::query()
            ->whereIn('id', $this->employeeIds)
            ->with('company')
            ->get()
            ->sortBy('name')
            ->values();

        if ($employees->isEmpty()) {
            return;
        }

        $months = collect($this->months)->sort()->values();

        $sheets = [];
        foreach ($employees as $employee) {
            foreach ($months as $m) {
                $periodStart = CarbonImmutable::createFromDate($this->year, (int) $m, 1)->startOfMonth();
                $periodEnd = $periodStart->endOfMonth();
                $sheets[] = $service->buildForEmployee($employee, $periodStart, $periodEnd);
            }
        }

        $html = view($this->view, [
            'sheets'    => $sheets,
            'printedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $options = new \Dompdf\Options(['defaultFont' => 'DejaVu Sans']);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = $this->buildFilename($employees, $months);

        $token = (string) Str::uuid();
        Storage::disk('local')->put("attendance-sheets/{$token}/{$filename}", $dompdf->output());

        $this->notifySuccess($token, $filename, $employees->count());
    }

    public function failed(Throwable $e): void
    {
        $user = User::find($this->requestedByUserId);
        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Nem sikerült elkészíteni a jelenléti ívet')
            ->body($this->label.' — hiba történt a generálás közben, próbáld újra.')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->sendToDatabase($user);
    }

    /** Egyetlen kiválasztott dolgozónál a fájlnév tartalmazza a nevét is (nem csak kötegelt nyomtatásnál). */
    private function buildFilename(\Illuminate\Support\Collection $employees, \Illuminate\Support\Collection $months): string
    {
        $filenameMonths = $months->implode('_') ?: now()->format('m');
        $namePart = $employees->count() === 1
            ? '_'.Str::slug($employees->first()->name, '_')
            : '';

        return $this->filenamePrefix.$namePart.'_'.$this->year.'_'.$filenameMonths.'.pdf';
    }

    private function notifySuccess(string $token, string $filename, int $employeeCount): void
    {
        $user = User::find($this->requestedByUserId);
        if (! $user) {
            return;
        }

        Notification::make()
            ->title('Elkészült: '.$this->label)
            ->body($employeeCount.' dolgozó jelenléti íve letölthető.')
            ->icon('heroicon-o-document-check')
            ->color('success')
            ->actions([
                Action::make('download')
                    ->label('Letöltés')
                    ->url(route('attendance-sheet-batch.download', ['token' => $token, 'filename' => $filename]))
                    ->openUrlInNewTab(),
            ])
            ->sendToDatabase($user);
    }

    /** A korábbi (7 napnál régebbi) generált fájlok törlése, hogy ne gyűljön a lemezen a személyes adat. */
    private function cleanupOldFiles(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDays(self::RETENTION_DAYS)->timestamp;

        foreach ($disk->directories('attendance-sheets') as $dir) {
            $files = $disk->files($dir);
            $lastModified = collect($files)->map(fn (string $f) => $disk->lastModified($f))->max();
            if ($lastModified !== null && $lastModified < $cutoff) {
                $disk->deleteDirectory($dir);
            }
        }
    }
}

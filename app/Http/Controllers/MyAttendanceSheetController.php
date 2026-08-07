<?php

namespace App\Http\Controllers;

use App\Services\AttendanceSheetService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyAttendanceSheetController extends Controller
{
    /**
     * A bejelentkezett felhasználó SAJÁT jelenléti íve — mindig Auth::user()->employee
     * alapján, sosem kliens-oldali employee_id-vel, hogy más dolgozó adata ne legyen elérhető.
     */
    public function download(int $monthsAgo = 0): StreamedResponse
    {
        return $this->render($monthsAgo, 'exports.attendance-sheet', 'jelenleti_iv');
    }

    /** Ugyanaz, de a "részletes" nézet — minden be-/kilépési szakasz külön sorban. */
    public function downloadDetailed(int $monthsAgo = 0): StreamedResponse
    {
        return $this->render($monthsAgo, 'exports.attendance-sheet-detailed', 'jelenleti_iv_reszletes');
    }

    private function render(int $monthsAgo, string $view, string $filenamePrefix): StreamedResponse
    {
        $employee = Auth::user()?->employee;
        abort_unless($employee, 403, 'Nincs a fiókodhoz rendelt dolgozói adatlap.');

        // 24 hónap visszamenőleg, hogy a korábbi (pl. tavalyi) importált jelenléti adatok is
        // elérhetők legyenek a dolgozó számára, ne csak az utolsó éj.
        $monthsAgo = max(0, min(24, $monthsAgo));

        $periodStart = CarbonImmutable::now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        $service = app(AttendanceSheetService::class);
        $sheet = $service->buildForEmployee($employee->loadMissing('company'), $periodStart, $periodEnd);

        $html = view($view, [
            'sheets'    => [$sheet],
            'printedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $options = new \Dompdf\Options(['defaultFont' => 'DejaVu Sans']);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // "inline", nem "attachment": alapértelmezésben megnyíljon (új böngésző-fülön),
        // ne letöltésre kényszerítse a felhasználót.
        return new StreamedResponse(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filenamePrefix.'_'.$periodStart->format('Y_m').'.pdf"',
        ]);
    }
}

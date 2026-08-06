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
        $employee = Auth::user()?->employee;
        abort_unless($employee, 403, 'Nincs a fiókodhoz rendelt dolgozói adatlap.');

        // 24 hónap visszamenőleg, hogy a korábbi (pl. tavalyi) importált jelenléti adatok is
        // elérhetők legyenek a dolgozó számára, ne csak az utolsó éj.
        $monthsAgo = max(0, min(24, $monthsAgo));

        $periodStart = CarbonImmutable::now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
        $periodEnd = $periodStart->endOfMonth();

        $service = app(AttendanceSheetService::class);
        $sheet = $service->buildForEmployee($employee->loadMissing('company'), $periodStart, $periodEnd);

        $html = view('exports.attendance-sheet', [
            'sheets'    => [$sheet],
            'printedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $options = new \Dompdf\Options(['defaultFont' => 'DejaVu Sans']);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new StreamedResponse(function () use ($dompdf) {
            echo $dompdf->output();
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="jelenleti_iv_'.$periodStart->format('Y_m').'.pdf"',
        ]);
    }
}

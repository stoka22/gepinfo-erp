<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Console\Command;

class GenerateTerminalWebhookTestPlanDocs extends Command
{
    protected $signature = 'docs:terminal-webhook-test-plan-pdf';

    protected $description = 'A terminál webhook élesben futtatható tesztterv PDF-jének legenerálása (a valós TERMINAL_SECRET-tel), a docs/ mappába.';

    public function handle(): int
    {
        $token = config('services.terminal.secret');

        if (empty($token)) {
            $this->error('A TERMINAL_SECRET nincs beállítva (.env) — a PDF nem generálható valós token nélkül.');
            return self::FAILURE;
        }

        $domain = 'gepinfo.hu';

        $html = view('exports.terminal-webhook-test-plan', [
            'domain'      => $domain,
            'endpoint'    => "https://{$domain}/api/terminal/event",
            'token'       => $token,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->render();

        $options = new Options(['defaultFont' => 'DejaVu Sans']);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $path = base_path('docs/terminal-webhook-test-plan.pdf');
        file_put_contents($path, $dompdf->output());

        $this->info("Kész: {$path}");
        $this->warn('Ez a fájl a valós titkos tokent tartalmazza — NE kerüljön verziókövetésbe (a .gitignore már kizárja).');

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\JumpCodeGeneratorV1;
use App\Services\JumpCodeGeneratorV2;
use App\Services\JumpCodeGeneratorV3;
use Throwable;

class JumpCodeController extends Controller
{
    public function __construct()
    {
        // Ha szükséges, itt adhatod hozzá a middleware-eket (pl. auth)
        // $this->middleware(['auth']);
    }

    /**
     * Válassza ki a nézetet: Filament page ha van, egyébként fallback.
     */
    protected function chooseViewName(): string
    {
       return 'jumpcodes.index';
    }

    /**
     * Megjeleníti az egyoldalas generátort.
     */
    public function index(): View
    {
        $viewName = $this->chooseViewName();
        return view($viewName);
    }

    /**
     * Generálja a kódot a kiválasztott variáns szerint.
     *
     * @param Request $request
     * @param JumpCodeGeneratorV1 $generatorV1  (alapértelmezett V1 service DI)
     * @return View|JsonResponse
     */
    public function generate(Request $request, JumpCodeGeneratorV1 $generatorV1): View|JsonResponse
    {
        $data = $request->validate([
            'key'     => ['required', 'string', 'regex:/^\d+$/'],
            'variant' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $key = $data['key'];
        $variant = (int) $data['variant'];

        try {
            $code = null;

            if ($variant === 1) {
                // V1: injektált service
                $code = $generatorV1->generate($key);
            } elseif ($variant === 2) {
                // V2: preferált a dedikált osztály, ha regisztrálva van
                if (class_exists(JumpCodeGeneratorV2::class)) {
                    /** @var JumpCodeGeneratorV2 $gen2 */
                    $gen2 = app()->make(JumpCodeGeneratorV2::class);
                    $code = $gen2->generate($key);
                } elseif (method_exists($generatorV1, 'generateVariant')) {
                    // fallback: a V1-ben implementált multi-variant metódus
                    $code = $generatorV1->generateVariant(2, $key);
                } else {
                    throw new \RuntimeException('Nincs regisztrált generátor a 2. variánsra.');
                }
            } else { // variant === 3
                if (class_exists(JumpCodeGeneratorV3::class)) {
                    /** @var JumpCodeGeneratorV3 $gen3 */
                    $gen3 = app()->make(JumpCodeGeneratorV3::class);
                    $code = $gen3->generate($key);
                } elseif (method_exists($generatorV1, 'generateVariant')) {
                    $code = $generatorV1->generateVariant(3, $key);
                } else {
                    throw new \RuntimeException('Nincs regisztrált generátor a 3. variánsra.');
                }
            }

            // Ha AJAX/JSON kérést kaptunk, JSON válasz
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'variant' => $variant,
                    'key'     => $key,
                    'code'    => $code,
                ]);
            }

            // Normál HTML nézet visszaadása eredménnyel
            $viewName = $this->chooseViewName();
            return view($viewName, [
                'key'     => $key,
                'variant' => $variant,
                'code'    => $code,
            ]);
        } catch (Throwable $e) {
            // teljes log a kontextussal - de a felhasználónak csak baráti üzenet
            Log::error('JumpCode generate error', [
                'variant' => $variant,
                'key'     => $key,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Generálás sikertelen.',
                ], 500);
            }

            $viewName = $this->chooseViewName();
            return view($viewName, [
                'key'     => $key,
                'variant' => $variant,
                'error'   => 'Generálás sikertelen. Kérjük próbáld újra.',
            ]);
        }
    }
}

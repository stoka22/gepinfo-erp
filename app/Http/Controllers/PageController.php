<?php

namespace App\Http\Controllers;

use App\Models\TrainingMaterial;
use App\Support\CompanyServices;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PageController extends Controller
{
    public function home(): View
    {
        return view('home', ['services' => CompanyServices::all()]);
    }

    public function training(): View
    {
        $materials = TrainingMaterial::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('oktatas', [
            'fileGroups' => $materials
                ->where('kind', 'file')
                ->groupBy(fn (TrainingMaterial $m) => $m->category ?? 'egyeb'),
            'videos' => $materials->where('kind', 'video')->values(),
        ]);
    }

    public function servicesIndex(): View
    {
        return view('szolgaltatasok.index', ['services' => CompanyServices::all()]);
    }

    public function servicesShow(string $slug): View|Response
    {
        $service = CompanyServices::find($slug);

        abort_if($service === null, 404);

        return view('szolgaltatasok.show', ['service' => $service]);
    }

    /**
     * A feltöltött oktatási fájlokat PHP-n (Laravel-en) keresztül szolgáljuk ki,
     * nem a public/storage szimlinken át statikusan — a szerver Apache-
     * konfigurációja ugyanis nem engedi a szimlinken keresztüli statikus
     * kiszolgálást (403), miközben a PHP-s elérés a tulajdonos jogaival fut,
     * és így mindig működik, a fájlrendszer-jogosultságoktól függetlenül.
     */
    public function downloadMaterial(TrainingMaterial $trainingMaterial): StreamedResponse
    {
        abort_unless($trainingMaterial->kind === 'file' && $trainingMaterial->file_path, 404);
        abort_unless(Storage::disk('public')->exists($trainingMaterial->file_path), 404);

        return Storage::disk('public')->download($trainingMaterial->file_path);
    }

    public function contact(): View
    {
        return view('kapcsolat');
    }

    public function privacy(): View
    {
        return view('adatvedelem');
    }
}

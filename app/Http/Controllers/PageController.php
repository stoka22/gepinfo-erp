<?php

namespace App\Http\Controllers;

use App\Models\TrainingMaterial;
use App\Support\CompanyServices;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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

    public function contact(): View
    {
        return view('kapcsolat');
    }
}

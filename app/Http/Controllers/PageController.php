<?php

namespace App\Http\Controllers;

use App\Support\CompanyServices;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PageController extends Controller
{
    public function home(): View
    {
        return view('home', ['services' => CompanyServices::all()]);
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

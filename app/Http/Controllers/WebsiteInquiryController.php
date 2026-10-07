<?php

namespace App\Http\Controllers;

use App\Models\WebsiteInquiry;
use App\Support\WebsiteInquiryQuestionnaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteInquiryController extends Controller
{
    public function show(): View
    {
        return view('weboldal-igenyfelmero', ['sections' => WebsiteInquiryQuestionnaire::sections()]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Spamcsapda: a mezőnek rejtve, üresen kell maradnia — ha egy bot kitölti,
        // úgy teszünk, mintha sikerült volna a beküldés, de nem mentünk semmit.
        if (filled($request->input('website'))) {
            return redirect()->route('weboldal-igenyfelmero')->with('success', true);
        }

        $rules = ['cegnev' => ['required', 'string', 'max:255']];

        foreach (WebsiteInquiryQuestionnaire::fields() as $key => $field) {
            if ($key === 'cegnev') {
                continue;
            }

            $rules[$key] = match ($field['type']) {
                'checkbox' => ['nullable', 'array'],
                'textarea' => ['nullable', 'string', 'max:5000'],
                'email' => ['nullable', 'email', 'max:255'],
                'date' => ['nullable', 'date'],
                default => ['nullable', 'string', 'max:500'],
            };

            if ($field['type'] === 'checkbox') {
                $rules["{$key}.*"] = [Rule::in(array_keys($field['options']))];
            } elseif ($field['type'] === 'radio') {
                $rules[$key][] = Rule::in(array_keys($field['options']));
            }
        }

        $validated = $request->validate($rules, [], self::labels());

        if (blank($validated['telefon'] ?? null) && blank($validated['email'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['telefon' => 'Adjon meg telefonszámot vagy e-mail címet, hogy fel tudjuk venni Önnel a kapcsolatot.']);
        }

        $answers = collect($validated)
            ->except(['cegnev', 'kapcsolattarto_neve', 'telefon', 'email'])
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->all();

        WebsiteInquiry::create([
            'cegnev' => $validated['cegnev'],
            'kapcsolattarto_neve' => $validated['kapcsolattarto_neve'] ?? null,
            'telefon' => $validated['telefon'] ?? null,
            'email' => $validated['email'] ?? null,
            'answers' => $answers,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('weboldal-igenyfelmero')->with('success', true);
    }

    /** @return array<string, string> */
    private static function labels(): array
    {
        return collect(WebsiteInquiryQuestionnaire::fields())
            ->map(fn (array $field) => $field['label'])
            ->all();
    }
}

<x-site-layout
    title="Weboldal igényfelmérő kérdőív | Gépinfo"
    description="Töltse ki az online igényfelmérő kérdőívet, hogy pontos árajánlatot tudjunk adni az új vagy megújuló weboldalára.">

    <section class="py-16 sm:py-20 bg-slate-50">
        <div class="mx-auto max-w-3xl px-4 sm:px-6">
            <nav aria-label="Morzsamenü" class="text-sm text-slate-500">
                <a href="{{ route('home') }}" class="hover:text-blue-600 transition">Főoldal</a>
                <span class="mx-1">/</span>
                <a href="{{ route('szolgaltatasok.show', 'weblap-keszites') }}" class="hover:text-blue-600 transition">Weblap készítés</a>
                <span class="mx-1">/</span>
                <span class="text-slate-700">Igényfelmérő kérdőív</span>
            </nav>

            <h1 class="mt-4 text-2xl sm:text-3xl font-extrabold text-slate-900">Weboldal igényfelmérő kérdőív</h1>
            <p class="mt-3 text-slate-600">
                A kérdőív kitöltése 10–15 percet vesz igénybe, és ez alapján készül a pontos árajánlat.
                Jelölje be, ami Önre érvényes; ahol nem biztos a válaszban, hagyja üresen, és a személyes
                egyeztetésen átbeszéljük.
            </p>

            @if (session('success'))
                <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-800">
                    Köszönjük a kitöltést! Hamarosan jelentkezünk a megadott elérhetőségen.
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800">
                    <p class="font-semibold">Kérjük, javítsa az alábbiakat:</p>
                    <ul class="mt-1 list-disc list-inside text-sm">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('weboldal-igenyfelmero.store') }}" class="mt-10 space-y-8">
                @csrf

                {{-- Spamcsapda: valódi látogató nem látja/tölti ki (lásd WebsiteInquiryController::store). --}}
                <div style="position:absolute; left:-9999px;" aria-hidden="true">
                    <label for="website">Weboldal</label>
                    <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                </div>

                @foreach ($sections as $section)
                    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm p-6 sm:p-8">
                        <h2 class="text-lg font-bold text-slate-900">{{ $section['title'] }}</h2>

                        <div class="mt-6 space-y-6">
                            @foreach ($section['fields'] as $field)
                                @php $key = $field['key']; @endphp
                                <div>
                                    <label for="field-{{ $key }}" class="block text-sm font-medium text-slate-700">
                                        {{ $field['label'] }}
                                    </label>

                                    @if (in_array($field['type'], ['text', 'email', 'date']))
                                        <input
                                            type="{{ $field['type'] }}"
                                            id="field-{{ $key }}"
                                            name="{{ $key }}"
                                            value="{{ old($key) }}"
                                            class="mt-1.5 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">
                                    @elseif ($field['type'] === 'textarea')
                                        <textarea
                                            id="field-{{ $key }}"
                                            name="{{ $key }}"
                                            rows="3"
                                            class="mt-1.5 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm shadow-sm outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100">{{ old($key) }}</textarea>
                                    @elseif ($field['type'] === 'radio')
                                        <div class="mt-2 space-y-2">
                                            @foreach ($field['options'] as $value => $label)
                                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                                    <input type="radio" name="{{ $key }}" value="{{ $value }}"
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500"
                                                           {{ old($key) === $value ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif ($field['type'] === 'checkbox')
                                        <div class="mt-2 space-y-2">
                                            @foreach ($field['options'] as $value => $label)
                                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                                    <input type="checkbox" name="{{ $key }}[]" value="{{ $value }}"
                                                           class="h-4 w-4 rounded text-blue-600 focus:ring-blue-500"
                                                           {{ in_array($value, old($key, []) ?? []) ? 'checked' : '' }}>
                                                    {{ $label }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    @error($key)
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white shadow hover:bg-blue-500 transition">
                        Kérdőív beküldése
                    </button>
                </div>
            </form>
        </div>
    </section>

</x-site-layout>

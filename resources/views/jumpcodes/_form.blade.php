{{-- Kártya – Filament 3 jellegű --}}
<div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-800/60">
  {{-- Kártya fejléce --}}
  <div class="flex items-center gap-3 px-6 pt-6">
    <x-heroicon-o-key class="icon-6 text-indigo-600 dark:text-indigo-400"/>
    <h2 class="text-2xl font-semibold tracking-tight">Ugrókód generátor</h2>
  </div>

  {{-- Kártya tartalom --}}
  <div class="px-6 pb-6 pt-4">
    @if(isset($error))
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 dark:border-red-900 dark:bg-red-900/25 dark:text-red-100">
        {{ $error }}
      </div>
    @endif

    <form id="jumpcode-form" action="{{ route('jumpcodes.generate') }}" method="POST" class="space-y-6">
      @csrf

      {{-- Mezők rácsa --}}
      <div class="grid gap-6 sm:grid-cols-3">
        <div class="sm:col-span-2">
          <label for="key" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Kulcs (csak számok)</label>
          <input id="key" name="key" type="text" inputmode="numeric" pattern="\d*"
                 value="{{ old('key', $key ?? '') }}"
                 class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-base shadow-sm outline-none transition
                        focus:border-indigo-500 focus:ring-4 focus:ring-indigo-200/60
                        dark:border-gray-700 dark:bg-gray-900/40 dark:focus:border-indigo-400"
                 placeholder="Pl. 165701" required>
          @error('key') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
          <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Változat</span>
          <div class="flex flex-col gap-2">
            @foreach ([1=>'Paraméter',2=>'GPS Temp',3=>'GPS Unlock'] as $val => $label)
              <label class="inline-flex items-center gap-2">
                <input type="radio" name="variant" value="{{ $val }}"
                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-400"
                       {{ (old('variant', $variant ?? 1) == $val) ? 'checked' : '' }}>
                <span class="text-sm">{{ $label }}</span>
              </label>
            @endforeach
          </div>
          @error('variant') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
        </div>
      </div>

      {{-- Akciósor – Filament-szerű gombok --}}
      <div class="flex flex-wrap items-center gap-3">
        <button type="submit"
          class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-white shadow
                 hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-300
                 dark:bg-indigo-500 dark:hover:bg-indigo-600 dark:focus:ring-indigo-800">
          <x-heroicon-m-sparkles class="icon-5"/>
          <span>Generálás</span>
        </button>

        <button type="button" id="generate-ajax"
          class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-gray-800 shadow-sm
                 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-200
                 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 dark:hover:bg-gray-900/70 dark:focus:ring-gray-800">
          <x-heroicon-m-bolt class="icon-5"/>
          <span>Generálás (AJAX)</span>
        </button>

        <button type="button" id="clear"
          class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-gray-800 shadow-sm
                 hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-gray-200
                 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 dark:hover:bg-gray-900/70 dark:focus:ring-gray-800">
          <x-heroicon-m-x-mark class="icon-5"/>
          <span>Törlés</span>
        </button>
      </div>
    </form>

    {{-- Eredmény szekció – Filament „section” érzet --}}
    <div id="result" class="mt-6">
      @if(isset($code))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900 dark:bg-green-900/25">
          <div class="mb-1 text-sm text-gray-700 dark:text-gray-300">Generált kód (V{{ $variant ?? 1 }}):</div>
          <div class="flex items-center justify-between gap-4">
            <div id="generated-code" class="text-3xl font-bold tracking-wider">{{ $code }}</div>
            <button id="copy-btn"
              class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm hover:bg-gray-50
                     focus:outline-none focus:ring-4 focus:ring-gray-200
                     dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 dark:hover:bg-gray-900/70 dark:focus:ring-gray-800">
              Másolás
            </button>
          </div>
        </div>
      @else
        <div id="generated-code" class="hidden"></div>
      @endif
    </div>
  </div>
</div>

{{-- Ikonméret fix (a korábbi „óriás” svg felülírására) --}}
<style>
  .icon-5 { width: 1.25rem; height: 1.25rem; display:inline-block; vertical-align: middle; }
  .icon-6 { width: 1.5rem;  height: 1.5rem;  display:inline-block; vertical-align: middle; }
  button svg, .icon-5, .icon-6 { max-width: none; max-height: none; }
</style>

<script>
(function(){
  const form     = document.getElementById('jumpcode-form');
  const ajaxBtn  = document.getElementById('generate-ajax');
  const resultBox= document.getElementById('result');
  const copyBtn  = document.getElementById('copy-btn');
  const clearBtn = document.getElementById('clear');

  ajaxBtn?.addEventListener('click', async function () {
    const formData = new FormData(form);
    try {
      ajaxBtn.disabled = true;
      ajaxBtn.innerText = 'Generálás…';
      const resp = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: formData
      });
      const json = await resp.json();
      if (json.success) {
        showResult(json.code, json.variant);
        toast('Kód elkészült.');
      } else {
        showError(json.error || 'Hiba történt a generálás során.');
      }
    } catch {
      showError('Kommunikációs hiba, kérlek próbáld meg újra.');
    } finally {
      ajaxBtn.disabled = false;
      ajaxBtn.innerText = 'Generálás (AJAX)';
    }
  });

  copyBtn?.addEventListener('click', () => copyCurrent());
  clearBtn?.addEventListener('click', () => { form.reset(); resultBox.innerHTML = ''; });

  function copyCurrent() {
    const el = document.getElementById('generated-code');
    if (!el) return;
    navigator.clipboard?.writeText(el.textContent.trim()).then(() => toast('Kimásolva a vágólapra.'));
  }

  function showResult(code, variant) {
    resultBox.innerHTML = `
      <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 dark:border-green-900 dark:bg-green-900/25">
        <div class="mb-1 text-sm text-gray-700 dark:text-gray-300">Generált kód (V${variant}):</div>
        <div class="flex items-center justify-between gap-4">
          <div id="generated-code" class="text-3xl font-bold tracking-wider">${code}</div>
          <button id="copy-btn"
            class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm hover:bg-gray-50
                   focus:outline-none focus:ring-4 focus:ring-gray-200
                   dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-100 dark:hover:bg-gray-900/70 dark:focus:ring-gray-800">
            Másolás
          </button>
        </div>
      </div>`;
    resultBox.querySelector('#copy-btn')?.addEventListener('click', copyCurrent);
  }

  function showError(msg) {
    resultBox.innerHTML = `
      <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700
                  dark:border-red-900 dark:bg-red-900/25 dark:text-red-100">${msg}</div>`;
  }

  // minimál toast – Filament érzet
  function toast(text) {
    const t = document.createElement('div');
    t.className = 'fixed bottom-6 right-6 z-50 rounded-lg bg-gray-900/90 px-4 py-2 text-sm text-white shadow-lg';
    t.textContent = text;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 1500);
  }
})();
</script>

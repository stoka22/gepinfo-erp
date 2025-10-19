{{-- resources/views/jumpcodes/_form.blade.php --}}
<div class="max-w-2xl mx-auto p-4 bg-white rounded shadow-sm">
    <h2 class="text-xl font-semibold mb-4">Ugrókód generátor</h2>

    @if(isset($error))
        <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded">
            {{ $error }}
        </div>
    @endif

    <form id="jumpcode-form" action="{{ route('jumpcodes.generate') }}" method="POST" class="space-y-4">
        @csrf

        <div>
            <label for="key" class="block text-sm font-medium text-gray-700">Kulcs (csak számok)</label>
            <input id="key" name="key" type="text" inputmode="numeric" pattern="\d*"
                   value="{{ old('key', $key ?? '') }}"
                   class="mt-1 block w-full border rounded px-3 py-2 focus:ring focus:ring-indigo-200"
                   placeholder="Pl. 165701" required>
            @error('key') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Válassz variánst</label>
            <div class="flex items-center space-x-4">
                <label class="inline-flex items-center">
                    <input type="radio" name="variant" value="1" class="form-radio" {{ (isset($variant) && $variant==1) || !isset($variant) ? 'checked' : '' }}>
                    <span class="ml-2">Paraméter</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="variant" value="2" class="form-radio" {{ isset($variant) && $variant==2 ? 'checked' : '' }}>
                    <span class="ml-2">GPS Temp</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="variant" value="3" class="form-radio" {{ isset($variant) && $variant==3 ? 'checked' : '' }}>
                    <span class="ml-2">GPS Unlock</span>
                </label>
            </div>
            @error('variant') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center space-x-3">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                Generál
            </button>

            <button type="button" id="generate-ajax" class="inline-flex items-center px-3 py-2 border rounded hover:bg-gray-50">
                Generál (AJAX)
            </button>

            <button type="button" id="clear" class="inline-flex items-center px-3 py-2 border rounded hover:bg-gray-50">
                Töröl
            </button>
        </div>
    </form>

    <div id="result" class="mt-4">
        @if(isset($code))
            <div class="p-3 bg-green-50 border border-green-100 rounded">
                <div class="text-sm text-gray-600 mb-1">Generált kód (V{{ $variant ?? 1 }}):</div>
                <div class="flex items-center justify-between">
                    <div id="generated-code" class="text-2xl font-bold tracking-wider">{{ $code }}</div>
                    <div class="flex items-center space-x-2">
                        <button id="copy-btn" class="px-3 py-1 border rounded text-sm">Másol</button>
                    </div>
                </div>
            </div>
        @else
            <div id="generated-code" class="hidden"></div>
        @endif
    </div>
</div>

<script>
(function(){
    const form = document.getElementById('jumpcode-form');
    const ajaxBtn = document.getElementById('generate-ajax');
    const resultBox = document.getElementById('result');
    const copyBtn = document.getElementById('copy-btn');
    const clearBtn = document.getElementById('clear');
    const codeEl = document.getElementById('generated-code');

    ajaxBtn?.addEventListener('click', async function () {
        const formData = new FormData(form);
        try {
            ajaxBtn.disabled = true;
            ajaxBtn.textContent = 'Generálás...';
            const resp = await fetch(form.action, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData
            });
            const json = await resp.json();
            if (json.success) {
                showResult(json.code, json.variant);
            } else {
                showError(json.error || 'Hiba történt a generálás során.');
            }
        } catch (e) {
            showError('Kommunikációs hiba, próbáld újra.');
        } finally {
            ajaxBtn.disabled = false;
            ajaxBtn.textContent = 'Generál (AJAX)';
        }
    });

    copyBtn?.addEventListener('click', function () {
        const txt = codeEl.textContent.trim();
        if (!txt) return;
        navigator.clipboard?.writeText(txt).then(() => {
            copyBtn.textContent = 'Másolva';
            setTimeout(()=> copyBtn.textContent = 'Másol', 1200);
        }).catch(() => {
            alert('Másolás sikertelen. Kézzel másold ki a kódot.');
        });
    });

    clearBtn?.addEventListener('click', function () {
        form.reset();
        codeEl.textContent = '';
        codeEl.classList.add('hidden');
        resultBox.querySelectorAll('.p-3').forEach(n => n.remove());
    });

    function showResult(code, variant) {
        // render minimal result UI
        resultBox.innerHTML = `
            <div class="p-3 bg-green-50 border border-green-100 rounded">
                <div class="text-sm text-gray-600 mb-1">Generált kód (V${variant}):</div>
                <div class="flex items-center justify-between">
                    <div id="generated-code" class="text-2xl font-bold tracking-wider">${code}</div>
                    <div class="flex items-center space-x-2">
                        <button id="copy-btn" class="px-3 py-1 border rounded text-sm">Másol</button>
                    </div>
                </div>
            </div>
        `;
        // rebind copy
        const newCopy = document.getElementById('copy-btn');
        const newCode = document.getElementById('generated-code');
        newCopy?.addEventListener('click', function () {
            navigator.clipboard?.writeText(newCode.textContent.trim()).then(() => {
                newCopy.textContent = 'Másolva';
                setTimeout(()=> newCopy.textContent = 'Másol', 1200);
            }).catch(()=> alert('Másolás sikertelen.'));
        });
    }

    function showError(msg) {
        resultBox.innerHTML = `<div class="p-3 bg-red-50 border border-red-100 text-red-700 rounded">${msg}</div>`;
    }
})();
</script>

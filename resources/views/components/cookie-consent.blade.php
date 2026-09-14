<div id="cookie-consent-banner"
     hidden
     class="fixed inset-x-0 bottom-0 z-[60] p-4 sm:p-6"
     role="dialog"
     aria-live="polite"
     aria-label="Sütikezelési tájékoztatás">
    <div class="mx-auto max-w-3xl rounded-2xl shadow-lg p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center gap-4"
         style="background:#0b1424; color:#e2e8f0; border:1px solid rgba(255,255,255,.1);">
        <p class="text-sm leading-relaxed flex-1">
            Ezen az oldalon a működéshez szükséges sütiket használunk, valamint az Oktatás oldalon
            beágyazott YouTube-videók lejátszásakor a Google is elhelyezhet sütiket. Bővebben a
            <a href="{{ route('adatvedelem') }}" style="color:#60a5fa;" class="hover:underline">Süti- és adatkezelési tájékoztatóban</a> olvashat.
        </p>
        <div class="flex gap-2 shrink-0">
            <button type="button" id="cookie-consent-necessary"
                    style="color:#e2e8f0; border:1px solid rgba(255,255,255,.25);"
                    class="rounded-lg px-4 py-2 text-sm font-semibold hover:opacity-80 transition">
                Csak a szükséges
            </button>
            <button type="button" id="cookie-consent-accept"
                    style="background:#2563eb; color:#ffffff;"
                    class="rounded-lg px-4 py-2 text-sm font-semibold hover:opacity-90 transition">
                Mind elfogadom
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var STORAGE_KEY = 'gepinfo_cookie_consent';

    function getConsent() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var data = JSON.parse(raw);
            return data && data.status ? data.status : null;
        } catch (e) {
            return null;
        }
    }

    function setConsent(status) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ status: status, ts: Date.now() }));
        } catch (e) {}
        var banner = document.getElementById('cookie-consent-banner');
        if (banner) banner.hidden = true;
        document.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: { status: status } }));
    }

    window.gepinfoCookieConsent = { getConsent: getConsent, setConsent: setConsent };

    document.addEventListener('DOMContentLoaded', function () {
        var banner = document.getElementById('cookie-consent-banner');
        if (!banner) return;

        if (getConsent() === null) {
            banner.hidden = false;
        }

        var acceptBtn = document.getElementById('cookie-consent-accept');
        var necessaryBtn = document.getElementById('cookie-consent-necessary');
        if (acceptBtn) acceptBtn.addEventListener('click', function () { setConsent('all'); });
        if (necessaryBtn) necessaryBtn.addEventListener('click', function () { setConsent('necessary'); });
    });
})();
</script>

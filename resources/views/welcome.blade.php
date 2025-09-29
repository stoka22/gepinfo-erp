<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="UTF-8">
        <title>Termelési áttekintő</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            :root{
                --bg: #000000;            /* jelenlegi háttér */
                --card: #fff;
                --border: #111;
                --shadow: #0007;
                --danger: #e11d48;
                --ok: #7CFC00;
                --orange: #ff9e2c;
            }
            html,body{height:100%}
            body{margin:0;background:var(--bg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
            .topbar{
                position:sticky;top:0;display:flex;gap:3px;align-items:center;
                justify-content:flex-end;padding:3px 2px;color:#fff;font-weight:700
            }
            .count{opacity:.9}
            .btn{
                background:#1f2937;color:#fff;border:none;border-radius:10px;padding:5px 27px;
                font-weight:600;cursor:pointer;box-shadow:0 1px 2px #0003
            }
            .btn:hover{opacity:.9}
            .wrap{padding:6px;display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;align-items:start}
            .card{
                background:var(--card); border:2px solid transparent;; border-radius:6px;
                padding:5px 5px 5px 5px; position:relative; box-shadow:0 4px 0 2px #000, inset 0 0 0 4px #1971d3;
            }
            .status-green{ outline:6px solid var(--ok); }
            .status-orange{ border-color: var(--orange); }
            .status-black{ border-color: #000; }
            .title{font-weight:800;font-size:22px;text-align:center;line-height:1.1;margin:4px 0 10px}
            .row{display:grid;grid-template-columns:1fr auto;gap:5px;padding:2px 4px;font-size:22px}
            .label{color:#111}
            .value{text-align:right}
            .sum{margin-top:8px;border-top:2px solid #f2f2f2;padding-top:6px;color:#c00;font-weight:700}
            .unit{font-size:18px}
        </style>
    </head>

    <body>
    <div class="topbar">
        <span class="count">Frissítés: <span id="cd">00:00</span></span>
        <a class="btn" href="{{ route('filament.user.auth.login') }}">Belépés</a>
    </div>

    <div class="wrap">
        @foreach($machines as $m)
            @php
                $cls = match($m->status){
                    'green'  => 'status-green',
                    'orange' => 'status-orange',
                    default  => 'status-black',
                };
            @endphp
            <div class="card {{ $cls }}">
                <div class="title">{{ $m->name }}</div>

                <div class="row">
                    <div class="label">Éj. :</div>
                    <div class="value">{{ number_format($m->shift_ej, 0, ',', ' ') }} <span class="unit">db</span></div>
                </div>
                <div class="row">
                    <div class="label">De. :</div>
                    <div class="value">{{ number_format($m->shift_de, 0, ',', ' ') }} <span class="unit">db</span></div>
                </div>
                <div class="row">
                    <div class="label">Du. :</div>
                    <div class="value">{{ number_format($m->shift_du, 0, ',', ' ') }} <span class="unit">db</span></div>
                </div>

                <div class="row sum">
                    <div class="label">Össz.:</div>
                    <div class="value">{{ number_format($m->osszesen, 0, ',', ' ') }} <span class="unit">db</span></div>
                </div>
            </div>
        @endforeach
    </div>
    <script>
    (function () {
        const cdEl = document.getElementById('cd');

        function msToNextMinute() {
            const now = new Date();
            return (60 - now.getSeconds()) * 1000 - now.getMilliseconds();
        }

        function updateCountdown() {
            const ms = msToNextMinute();
            const s  = Math.ceil(ms / 1000);
            const mm = String(Math.floor(s / 60)).padStart(2,'0');
            const ss = String(s % 60).padStart(2,'0');
            cdEl.textContent = `${mm}:${ss}`;
        }

        // első beállítás + 200ms-onként frissítjük a kijelzést
        updateCountdown();
        setInterval(updateCountdown, 200);

        // tényleges újratöltés a következő egész percnél
        setTimeout(() => window.location.reload(), msToNextMinute());
    })();
    </script>
</body>
</html>

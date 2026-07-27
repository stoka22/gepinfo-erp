<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }

        h2 { margin: 0 0 6px 0; font-size: 16px; }

        table.header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.header-table td { border: none; padding: 1px 0; font-size: 11px; }

        table.days-table { width: 100%; border-collapse: collapse; }
        table.days-table th, table.days-table td { border: 1px solid #333; padding: 3px 5px; text-align: left; }
        table.days-table th { background-color: #f2f2f2; }

        .holiday-row { background-color: #f8dede; }
        .weekend-row { background-color: #f0f0f0; }

        .sig-col { width: 100px; }
        .num-col { width: 55px; }
        .day-col { width: 30px; text-align: center; }

        .meta { font-size: 9px; color: #555; margin-top: 4px; }
    </style>
</head>
<body>
@foreach($sheets as $sheet)
    <div class="sheet">
        <h2>Jelenléti ív — {{ $sheet['periodLabel'] }}</h2>

        <table class="header-table">
            <tr>
                <td><strong>Név:</strong> {{ $sheet['employeeName'] }}</td>
                <td><strong>Munkáltató:</strong> {{ $sheet['companyName'] ?? '—' }}</td>
                <td><strong>Nyomtatás időpontja:</strong> {{ $printedAt }}</td>
            </tr>
            <tr>
                <td colspan="3">
                    <strong>Szabadság:</strong>
                    Keret: {{ number_format($sheet['vacation']['entitled'], 1) }} nap,
                    Felhasznált: {{ number_format($sheet['vacation']['used'], 1) }} nap,
                    Kivehető: {{ number_format($sheet['vacation']['remaining'], 1) }} nap
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <strong>Túlóra (egyenleg):</strong>
                    Összes éves: {{ $sheet['overtime']['yearly'] }},
                    Aktuális havi: {{ $sheet['overtime']['monthly'] }}
                </td>
            </tr>
            <tr>
                <td colspan="3">
                    <strong>Ledolgozott munkaóra:</strong>
                    Összes éves: {{ $sheet['workedHours']['yearly'] }},
                    Aktuális havi: {{ $sheet['workedHours']['monthly'] }}
                </td>
            </tr>
        </table>

        <table class="days-table">
            <thead>
                <tr>
                    <th class="day-col">Nap</th>
                    <th>Dátum</th>
                    <th>Érkezés</th>
                    <th>Távozás</th>
                    <th class="num-col">Ledolgozott</th>
                    <th class="num-col">Túlóra</th>
                    <th>Megjegyzés</th>
                    <th class="sig-col">Aláírás</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sheet['days'] as $day)
                    <tr class="{{ $day['isHoliday'] ? 'holiday-row' : ($day['isWeekend'] ? 'weekend-row' : '') }}">
                        <td class="day-col">{{ $day['dayNumber'] }}</td>
                        <td>{{ $day['date'] }} ({{ $day['dayName'] }})</td>
                        <td>{{ $day['start'] ?? '' }}</td>
                        <td>{{ $day['end'] ?? '' }}</td>
                        <td>{{ $day['hoursLabel'] ?? '' }}</td>
                        <td>{{ $day['overtimeLabel'] ?? '' }}</td>
                        <td>{{ $day['note'] }}</td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="meta">Az árnyékolt sorok munkaszüneti napot vagy pihenőnapot jelölnek. A visszamenőleges időszakra rögzített jelenléti adatok automatikusan feltöltve.</p>
    </div>
@endforeach
</body>
</html>

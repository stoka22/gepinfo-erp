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
        .segment-row td { color: #333; }
        .segment-row .day-col, .segment-row .date-col { color: #999; }

        .num-col { width: 55px; }
        .day-col { width: 30px; text-align: center; }

        .meta { font-size: 9px; color: #555; margin-top: 4px; }
    </style>
</head>
<body>
@foreach($sheets as $sheet)
    <div class="sheet">
        <h2>Jelenléti ív — részletes (soronkénti be-/kilépések) — {{ $sheet['periodLabel'] }}</h2>

        <table class="header-table">
            <tr>
                <td><strong>Név:</strong> {{ $sheet['employeeName'] }}</td>
                <td><strong>Munkáltató:</strong> {{ $sheet['companyName'] ?? '—' }}</td>
                <td><strong>Nyomtatás időpontja:</strong> {{ $printedAt }}</td>
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
                    <th>Helyiség</th>
                    <th>Megjegyzés</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sheet['days'] as $day)
                    @php $rowClass = $day['isHoliday'] ? 'holiday-row' : ($day['isWeekend'] ? 'weekend-row' : ''); @endphp
                    @if (count($day['segments']) > 0)
                        @foreach($day['segments'] as $i => $segment)
                            <tr class="{{ $rowClass }} {{ $i > 0 ? 'segment-row' : '' }}">
                                <td class="day-col">{{ $i === 0 ? $day['dayNumber'] . ($day['isModified'] ? '*' : '') : '' }}</td>
                                <td>{{ $i === 0 ? $day['date'] . ' (' . $day['dayName'] . ')' : '' }}</td>
                                <td>{{ $segment['start'] ?? '' }}</td>
                                <td>{{ $segment['end'] ?? '' }}</td>
                                <td>{{ $segment['hoursLabel'] ?? '' }}{{ $segment['isModified'] ? '*' : '' }}</td>
                                <td>{{ $segment['location'] ?? '' }}</td>
                                <td>{{ $i === 0 ? $day['note'] : '' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr class="{{ $rowClass }}">
                            <td class="day-col">{{ $day['dayNumber'] }}</td>
                            <td>{{ $day['date'] }} ({{ $day['dayName'] }})</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>{{ $day['note'] }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right"><strong>Havi túlóra összesen:</strong></td>
                    <td><strong>{{ $sheet['overtime']['monthly'] }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>

        <p class="meta">Ez a részletes nézet minden egyes be-/kilépési szakaszt külön sorban mutat (pl. ebédszünet miatti két be-/kilépés is látszik). Az árnyékolt sorok munkaszüneti napot vagy pihenőnapot jelölnek. A * jelölés utólagosan javított/módosított bejegyzést jelent.</p>
    </div>
@endforeach
</body>
</html>

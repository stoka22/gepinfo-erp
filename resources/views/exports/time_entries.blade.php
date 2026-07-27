<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div style="text-align: center; margin-bottom: 20px;">
        <h2>Jelenléti adatok</h2>
        <p><strong>Dolgozó neve:</strong> {{ $employeeName }}</p>
        <p><strong>Munkaadó:</strong> {{ $employerName }}</p>
        <p><strong>Szűrési feltétel:</strong> {{ $filterDescription }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Típus</th>
                <th>Dátum</th>
                
                <th>Kezdés</th>
                <th>Vége</th>
                <th>Idő (óra)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
                <tr>
                    <td>{{ $entry->type ? __('types.' . $entry->type->value) : '-' }}</td>
                    <td>{{ \Carbon\Carbon::parse($entry->start_date)->format('Y-m-d') }} - 
                    {{ \Carbon\Carbon::parse($entry->start_date)->locale('hu')->dayName }}</td>
                    <td>{{ \Carbon\Carbon::parse($entry->start_time)->format('H:i') }}</td>
                    <td>{{ \Carbon\Carbon::parse($entry->end_time)->format('H:i') }}</td>
                    <td>{{ number_format($entry->hours, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
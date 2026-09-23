<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 8px; }
        h1 { font-size: 14px; }
        p { font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 2px 3px; text-align: center; }
        th { background: #f0f0f0; }
        td.name { text-align: left; white-space: nowrap; }
        td.weekend { background: #f7f7f7; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>
        K — keldi, K/K — kech keldi, K/E — erta ketdi, N — kelmadi, X — xizmat safarida,
        T — ta'tilda, B — bemor varaqasida, D — dam olish kuni
    </p>
    <table>
        <thead>
            <tr>
                <th>Tabel raqami</th>
                <th>F.I.Sh.</th>
                <th>Bo'lim</th>
                @for ($day = 1; $day <= $timesheet['day_count']; $day++)
                    <th>{{ $day }}</th>
                @endfor
                <th>K</th>
                <th>K/K</th>
                <th>K/E</th>
                <th>N</th>
                <th>X</th>
                <th>T</th>
                <th>B</th>
                <th>Soat</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($timesheet['employees'] as $row)
                <tr>
                    <td class="name">{{ $row['employee_number'] }}</td>
                    <td class="name">{{ $row['full_name'] }}</td>
                    <td class="name">{{ $row['department'] }}</td>
                    @foreach ($row['days'] as $day)
                        <td class="{{ $day['is_weekend'] ? 'weekend' : '' }}">
                            {{ $day['short_code'] ?? ($day['is_weekend'] ? 'D' : '') }}
                        </td>
                    @endforeach
                    <td>{{ $row['totals']['present_count'] }}</td>
                    <td>{{ $row['totals']['late_count'] }}</td>
                    <td>{{ $row['totals']['early_leave_count'] }}</td>
                    <td>{{ $row['totals']['absent_count'] }}</td>
                    <td>{{ $row['totals']['business_trip_count'] }}</td>
                    <td>{{ $row['totals']['vacation_count'] }}</td>
                    <td>{{ $row['totals']['sick_leave_count'] }}</td>
                    <td>{{ round($row['totals']['total_worked_minutes'] / 60, 1) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

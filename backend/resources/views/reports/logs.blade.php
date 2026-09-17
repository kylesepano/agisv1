<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { color: #172554; font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .meta { color: #64748b; margin-bottom: 18px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #e0f2fe; text-align: left; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; word-break: break-word; }
        tr:nth-child(even) { background: #f8fafc; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>{{ $report['title'] }}</h1>
    <div class="meta">Generated {{ $report['generatedAt'] }} · {{ count($report['rows']) }} record(s)</div>
    <table>
        <thead><tr>@foreach ($report['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($report['rows'] as $row)
            <tr>@foreach ($report['columns'] as $column)<td>{{ $row[$column] ?? '' }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ max(1, count($report['columns'])) }}">No matching records.</td></tr>
        @endforelse
        </tbody>
    </table>
    <script>if (new URLSearchParams(window.location.search).get('format') === 'print') window.print();</script>
</body>
</html>

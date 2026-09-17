<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #64748b; padding: 6px; vertical-align: top; }
        h1 { color: #075985; }
        .heading { background: #0c4a6e; color: white; font-weight: bold; }
        .meta-label { background: #e2e8f0; font-weight: bold; }
    </style>
</head>
<body>
<h1>{{ $report['title'] }}</h1>
<p>{{ $report['description'] }}</p>
<table>
    @foreach($report['meta'] as $item)
        <tr>
            <td class="meta-label">{{ $item['label'] }}</td>
            <td>{{ $item['value'] }}</td>
        </tr>
    @endforeach
</table>
<br>
<table>
    <thead>
    <tr>
        @foreach($report['columns'] as $column)
            <th class="heading">{{ $column['label'] }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($report['rows'] as $row)
        <tr>
            @foreach($report['columns'] as $column)
                <td>{{ $row[$column['key']] ?? '' }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>

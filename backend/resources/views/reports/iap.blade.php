<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 22px 24px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1e293b; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; line-height: 1.4; }
        .header { border-bottom: 2px solid #075985; margin-bottom: 14px; padding-bottom: 10px; text-align: center; }
        .agency { color: #075985; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        h1 { color: #0f172a; font-size: 17px; margin: 5px 0 2px; }
        .description { color: #64748b; margin: 0 auto; max-width: 760px; }
        .meta { margin: 0 0 14px; width: 100%; }
        .meta td { background: #f1f5f9; border: 1px solid #cbd5e1; padding: 5px 7px; width: 25%; }
        .meta strong { color: #475569; display: block; font-size: 7px; letter-spacing: .04em; text-transform: uppercase; }
        h2 { color: #0f172a; font-size: 11px; margin: 13px 0 5px; }
        .section-item { border-left: 3px solid #0ea5e9; margin-bottom: 6px; padding: 4px 7px; }
        .section-item strong { color: #0f172a; display: block; }
        table.data { border-collapse: collapse; page-break-inside: auto; table-layout: auto; width: 100%; }
        .data th { background: #0c4a6e; border: 1px solid #075985; color: white; font-size: 7px; padding: 5px; text-align: left; text-transform: uppercase; }
        .data td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: top; word-break: break-word; }
        .data tr:nth-child(even) td { background: #f8fafc; }
        .data tr { page-break-inside: avoid; }
        .empty { border: 1px solid #cbd5e1; color: #64748b; padding: 18px; text-align: center; }
        .heat { border-collapse: collapse; margin: 8px auto 14px; width: 70%; }
        .heat th, .heat td { border: 1px solid #cbd5e1; padding: 9px; text-align: center; }
        .heat th { background: #e2e8f0; color: #334155; }
        .heat .critical { background: #fecaca; color: #991b1b; }
        .heat .high { background: #fed7aa; color: #9a3412; }
        .heat .medium { background: #fef3c7; color: #92400e; }
        .heat .low { background: #d1fae5; color: #065f46; }
        .footer { border-top: 1px solid #cbd5e1; color: #64748b; font-size: 7px; margin-top: 12px; padding-top: 6px; text-align: right; }
        @media print {
            .print-button { display: none !important; }
            body { padding: 0; }
        }
        @media screen {
            body { background: #eef5fa; padding: 22px; }
            .sheet { background: white; box-shadow: 0 10px 30px rgba(15, 23, 42, .15); margin: auto; max-width: 1200px; padding: 28px; }
            .print-button { background: #0369a1; border: 0; border-radius: 7px; color: white; cursor: pointer; font-weight: 700; margin-bottom: 15px; padding: 10px 18px; }
        }
    </style>
</head>
<body>
<div class="sheet">
    @if($print)
        <button class="print-button" onclick="window.print()">Print report / Save as PDF</button>
    @endif

    <header class="header">
        <div class="agency">{{ $configuration['organizationName'] }} · {{ $configuration['systemName'] }}</div>
        <h1>{{ $report['title'] }}</h1>
        <p class="description">{{ $report['description'] }}</p>
    </header>

    @if(count($report['meta']))
        <table class="meta">
            @foreach(array_chunk($report['meta'], 4) as $group)
                <tr>
                    @foreach($group as $item)
                        <td>
                            <strong>{{ $item['label'] }}</strong>
                            {{ $item['value'] }}
                        </td>
                    @endforeach
                    @for($index = count($group); $index < 4; $index++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif

    @foreach($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>
        @foreach($section['items'] as $item)
            @if(!empty($item['text']))
                <div class="section-item">
                    <strong>{{ $item['heading'] }}</strong>
                    {{ $item['text'] }}
                </div>
            @endif
        @endforeach
    @endforeach

    @if(($report['visualization']['type'] ?? null) === 'riskHeatMap')
        <h2>Inherent-to-Residual Risk Heat Map</h2>
        <table class="heat">
            <tr>
                <th>Inherent \ Residual</th>
                @foreach($report['visualization']['levels'] as $level)
                    <th>{{ ucfirst(strtolower($level)) }}</th>
                @endforeach
            </tr>
            @foreach($report['visualization']['matrix'] as $row)
                <tr>
                    <th>{{ ucfirst(strtolower($row['inherent'])) }}</th>
                    @foreach($row['cells'] as $cell)
                        <td class="{{ strtolower($cell['residual']) }}">
                            <strong>{{ $cell['value'] }}</strong>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Report Details</h2>
    @if(count($report['rows']))
        <table class="data">
            <thead>
            <tr>
                @foreach($report['columns'] as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($report['rows'] as $row)
                <tr>
                    @foreach($report['columns'] as $column)
                        <td>{{ $row[$column['key']] ?? '—' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No records matched this report.</div>
    @endif

    <footer class="footer">
        Generated by AGIS on {{ \Carbon\Carbon::parse($report['generatedAt'])->format('F j, Y g:i A') }}
    </footer>
</div>

@if($print)
<script>
    if (new URLSearchParams(window.location.search).get('autoprint') === '1') {
        window.addEventListener('load', () => window.print());
    }
</script>
@endif
</body>
</html>

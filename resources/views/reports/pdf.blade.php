<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #18181b; font-size: 11px; }
        .head { border-bottom: 2px solid #7c1d1d; padding-bottom: 8px; margin-bottom: 12px; }
        .head h1 { margin: 0; font-size: 18px; color: #7c1d1d; }
        .head .meta { color: #71717a; font-size: 10px; margin-top: 2px; }
        .summary { margin-bottom: 12px; }
        .summary span { display: inline-block; border: 1px solid #e4e4e7; border-radius: 6px; padding: 6px 10px; margin: 0 6px 6px 0; }
        .summary span b { display: block; font-size: 13px; }
        .summary span small { color: #71717a; font-size: 9px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f4f4f5; text-align: left; padding: 6px 8px; border-bottom: 1px solid #d4d4d8; font-size: 10px; text-transform: uppercase; color: #52525b; }
        td { padding: 5px 8px; border-bottom: 1px solid #efefef; }
        td.right, th.right { text-align: right; }
        .foot { margin-top: 14px; color: #a1a1aa; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $fmt = function ($value, $format) {
            if ($format === 'money') return '₹ '.number_format((float) $value, 2);
            if ($format === 'percent') return $value.'%';
            if ($format === 'days') return $value.' days';
            return $value;
        };
    @endphp

    <div class="head">
        <h1>{{ $title }}</h1>
        <div class="meta">
            {{ config('app.name', 'Car4Sales') }} ·
            @if(!empty($range['date_from']) || !empty($range['date_to']))
                {{ $range['date_from'] ?? '…' }} to {{ $range['date_to'] ?? '…' }} ·
            @endif
            Generated {{ $generatedAt->format('d M Y, h:i A') }}
        </div>
    </div>

    @if(count($result->summary))
        <div class="summary">
            @foreach($result->summary as $item)
                <span><small>{{ $item['label'] }}</small><b>{{ $fmt($item['value'], $item['format'] ?? null) }}</b></span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($result->columns as $col)
                    <th class="{{ ($col['align'] ?? '') === 'right' ? 'right' : '' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($result->rows as $row)
                <tr>
                    @foreach($result->columns as $col)
                        <td class="{{ ($col['align'] ?? '') === 'right' ? 'right' : '' }}">{{ $fmt($row[$col['key']] ?? '', $col['format'] ?? null) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($result->columns) }}" style="text-align:center; color:#a1a1aa; padding:16px;">No data for the selected period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">This is a computer-generated report from {{ config('app.name', 'Car4Sales') }}.</div>
</body>
</html>

{{-- Base layout for all generated PDF documents. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Document')</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1a1a1a; margin: 0; }
        .page { padding: 32px 40px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .company { font-size: 18px; font-weight: bold; }
        .muted { color: #666; }
        .doc-meta { text-align: right; font-size: 11px; }
        .doc-meta .num { font-weight: bold; font-size: 13px; }
        h1.title { font-size: 16px; text-align: center; margin: 22px 0 4px; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.kv td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; }
        table.kv td.label { background: #f5f5f5; font-weight: bold; width: 32%; }
        .section-title { margin-top: 18px; font-weight: bold; font-size: 13px; border-bottom: 1px solid #bbb; padding-bottom: 3px; }
        .signatures { margin-top: 48px; width: 100%; }
        .signatures td { width: 50%; padding-top: 40px; }
        .sign-line { border-top: 1px solid #333; padding-top: 4px; width: 80%; }
        .footer { position: fixed; bottom: 12px; left: 40px; right: 40px; font-size: 9px; color: #888;
                  border-top: 1px solid #ddd; padding-top: 6px; }
        .qr { text-align: right; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <div class="company">{{ config('app.name') }}</div>
                @isset($branch)
                    <div class="muted">{{ $branch->name }}@if($branch->city), {{ $branch->city }}@endif</div>
                    @if($branch->gst_number)<div class="muted">GSTIN: {{ $branch->gst_number }}</div>@endif
                @endisset
            </div>
            <div class="doc-meta">
                <div class="num">{{ $document_number }}</div>
                <div>{{ $generated_at->format('d M Y, h:i A') }}</div>
                <div>By: {{ $generated_by->name }}</div>
                @if(!empty($qr_data_uri))<img class="qr" src="{{ $qr_data_uri }}" width="80" height="80" alt="QR">@endif
            </div>
        </div>

        @yield('content')

        <div class="footer">
            {{ $document_number }} · Generated {{ $generated_at->format('d M Y H:i') }} ·
            This is a system-generated document of {{ config('app.name') }}.
        </div>
    </div>
</body>
</html>

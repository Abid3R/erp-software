<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { border-bottom: 2px solid #333; margin-bottom: 16px; padding-bottom: 8px; }
        .company { font-size: 18px; font-weight: bold; }
        .company .legal { font-weight: normal; font-size: 11px; color: #555; }
        .title { font-size: 15px; margin-top: 4px; }
        .meta { color: #666; font-size: 11px; margin-top: 2px; }
        h3 { font-size: 13px; margin: 16px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 4px 6px; border-bottom: 1px solid #ddd; }
        th { background: #f3f4f6; }
        td.num, th.num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; }
        .footer { margin-top: 24px; color: #888; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    @php($setting = $setting ?? null)
    <div class="header">
        @if ($setting?->logoDataUri())
            <img src="{{ $setting->logoDataUri() }}" style="max-height: 56px; margin-bottom: 6px;" alt="logo">
        @endif
        <div class="company">
            {{ $company?->name ?? config('app.name') }}
            @if ($company?->legal_name)
                <span class="legal">— {{ $company->legal_name }}</span>
            @endif
        </div>
        @if ($setting?->header_note)
            <div class="meta">{{ $setting->header_note }}</div>
        @endif
        <div class="title">@yield('title')</div>
        <div class="meta">@yield('period')</div>
    </div>

    @yield('content')

    <div class="footer">
        @if ($setting?->footer_note)
            {{ $setting->footer_note }}<br>
        @endif
        Generated {{ now()->format('Y-m-d H:i') }} · {{ config('app.name') }}
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_starts_with(app()->getLocale(), 'bn') ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Print')</title>
    <style>
        * { box-sizing: border-box; }
        /* OS Bengali fonts (Nirmala UI on Windows, others cross-platform) so Bangla
           shapes correctly; falls back to Latin UI fonts. */
        body {
            font-family: 'Nirmala UI', 'Noto Sans Bengali', 'SolaimanLipi', 'Kalpurush', 'Segoe UI', Arial, sans-serif;
            color: #111; margin: 0; background: #f3f4f6;
        }
        .toolbar { text-align: center; margin: 12px; }
        .btn { background: #1f2937; color: #fff; border: 0; padding: 8px 18px; border-radius: 6px; font-size: 14px; cursor: pointer; }
        .sheet { background: #fff; width: 210mm; min-height: 140mm; margin: 16px auto; padding: 16mm; box-shadow: 0 1px 6px rgba(0,0,0,.15); }
        .head { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 14px; }
        .company { font-size: 20px; font-weight: bold; }
        .company .legal { font-weight: normal; font-size: 12px; color: #555; }
        .doc-title { font-size: 16px; margin-top: 6px; }
        .meta { color: #555; font-size: 12px; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #ddd; }
        th { background: #f3f4f6; }
        .num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 2px solid #333; }
        .sign { margin-top: 60px; }
        .sign td { border: none; border-top: 1px solid #333; text-align: center; width: 40%; }
        .sign td.gap { border: none; width: 20%; }
        .footer { margin-top: 20px; text-align: center; color: #888; font-size: 10px; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; width: auto; padding: 0; }
            @page { size: A4; margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>
    <div class="sheet">
        <div class="head">
            <div class="company">
                {{ $company?->name ?? config('app.name') }}
                @if ($company?->legal_name)
                    <span class="legal">— {{ $company->legal_name }}</span>
                @endif
            </div>
            <div class="doc-title">@yield('title')</div>
            <div class="meta">@yield('meta')</div>
        </div>

        @yield('content')

        <div class="footer">{{ config('app.name') }} · Printed {{ now()->format('Y-m-d H:i') }}</div>
    </div>

    @if (request()->boolean('auto'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>

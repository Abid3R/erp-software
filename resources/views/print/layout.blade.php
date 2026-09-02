<!DOCTYPE html>
<html lang="{{ str_starts_with(app()->getLocale(), 'bn') ? 'bn' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Print')</title>
    <style>
        * { box-sizing: border-box; }
        /* OS Bengali fonts (Nirmala UI on Windows, others cross-platform) so Bangla
           and the ৳ sign shape correctly; falls back to Latin UI fonts. */
        html, body {
            font-family: 'Nirmala UI', 'Noto Sans Bengali', 'SolaimanLipi', 'Kalpurush', 'Segoe UI', Arial, sans-serif;
            color: #1a1d29; margin: 0; background: #e9edf2;
            -webkit-print-color-adjust: exact; print-color-adjust: exact;
        }

        .toolbar { text-align: center; margin: 14px; }
        .btn {
            background: #1e3a5f; color: #fff; border: 0; padding: 9px 22px;
            border-radius: 6px; font-size: 14px; cursor: pointer; font-weight: 600;
        }
        .btn:hover { background: #16304f; }

        /* A4 sheet */
        .sheet {
            background: #fff; width: 210mm; min-height: 297mm; margin: 18px auto;
            padding: 16mm 15mm 20mm; box-shadow: 0 2px 12px rgba(0,0,0,.18);
            position: relative;
        }

        /* ---- Letterhead ---- */
        .letterhead { border-bottom: 3px solid #1e3a5f; padding-bottom: 12px; margin-bottom: 4px; }
        .letterhead table { width: 100%; border-collapse: collapse; }
        .letterhead td { border: none; padding: 0; vertical-align: top; }
        .lh-logo { width: 78px; }
        .lh-logo img { max-height: 70px; max-width: 72px; }
        .company-name { font-size: 22px; font-weight: 700; color: #1e3a5f; letter-spacing: .2px; line-height: 1.15; }
        .company-legal { font-weight: 400; font-size: 12px; color: #5a6472; }
        .company-contact { font-size: 11px; color: #4a5464; margin-top: 3px; line-height: 1.5; }
        .company-bin { font-size: 11px; color: #1e3a5f; font-weight: 600; margin-top: 2px; }

        /* ---- Document title band ---- */
        .doc-band { text-align: center; margin: 14px 0 4px; }
        .doc-title {
            display: inline-block; font-size: 15px; font-weight: 700; color: #1a1d29;
            text-transform: uppercase; letter-spacing: 1.5px;
            border: 1.5px solid #1e3a5f; border-radius: 4px; padding: 5px 26px;
            background: #f4f7fb;
        }
        .doc-meta { font-size: 11.5px; color: #4a5464; margin-top: 7px; line-height: 1.6; }

        /* ---- Content tables ---- */
        .sheet h3 {
            font-size: 12.5px; margin: 18px 0 5px; color: #1e3a5f;
            text-transform: uppercase; letter-spacing: .6px; font-weight: 700;
            border-bottom: 1px solid #d5dde6; padding-bottom: 3px;
        }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 11.5px; }
        th, td { text-align: left; padding: 7px 9px; border-bottom: 1px solid #e2e8ef; }
        thead th {
            background: #1e3a5f; color: #fff; font-weight: 600; font-size: 11px;
            text-transform: uppercase; letter-spacing: .4px; border-bottom: 1px solid #1e3a5f;
        }
        tbody tr:nth-child(even) td { background: #f6f9fc; }
        .num { text-align: right; }
        tr.total td { font-weight: 700; border-top: 2px solid #1e3a5f; border-bottom: 2px solid #1e3a5f; background: #eef3f9 !important; color: #1a1d29; }

        /* Simple label/value tables (payslip, statement headers) keep clean look */
        table.plain td, table.plain th { border-bottom: 1px solid #eef2f6; }

        /* ---- Signatures ---- */
        table.sign { margin-top: 54px; }
        table.sign td {
            border: none; border-top: 1.2px solid #33404f; text-align: center;
            width: 30%; padding-top: 6px; font-size: 11px; color: #33404f; font-weight: 600;
            vertical-align: bottom;
        }
        table.sign td.gap { border: none; width: 5%; }

        /* ---- Footer ---- */
        .doc-footer {
            margin-top: 26px; border-top: 1px solid #d5dde6; padding-top: 8px;
            text-align: center; color: #8a94a2; font-size: 9.5px; line-height: 1.6;
        }
        .doc-footer .note-line { color: #5a6472; font-style: italic; }

        @media print {
            html, body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; width: auto; min-height: auto; padding: 0; }
            @page { size: A4; margin: 14mm; }
            thead { display: table-header-group; }   /* repeat table headers across pages */
            tr, .sign, .doc-footer { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">🖨 Print / Save as PDF</button>
    </div>

    @php($setting = $setting ?? null)
    <div class="sheet">

        <div class="letterhead">
            <table>
                <tr>
                    @if ($setting?->logoUrl())
                        <td class="lh-logo"><img src="{{ $setting->logoUrl() }}" alt="logo"></td>
                    @endif
                    <td>
                        <div class="company-name">
                            {{ $company?->name ?? config('app.name') }}
                            @if ($company?->legal_name)
                                <span class="company-legal">— {{ $company->legal_name }}</span>
                            @endif
                        </div>
                        @if ($setting?->header_note)
                            <div class="company-contact">{{ $setting->header_note }}</div>
                        @endif
                        @if ($company?->tax_registration_number)
                            <div class="company-bin">BIN / VAT Reg: {{ $company->tax_registration_number }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="doc-band">
            <div class="doc-title">@yield('title')</div>
            <div class="doc-meta">@yield('meta')</div>
        </div>

        @yield('content')

        <div class="doc-footer">
            @if ($setting?->footer_note)
                <span class="note-line">{{ $setting->footer_note }}</span><br>
            @endif
            This is a computer-generated document. &nbsp;·&nbsp;
            {{ $company?->name ?? config('app.name') }} &nbsp;·&nbsp;
            Printed {{ now()->format('d M Y, h:i A') }}
        </div>
    </div>

    @if (request()->boolean('auto'))
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>

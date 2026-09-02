<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        /* DomPDF renders server-side with the bundled DejaVu Sans (Unicode Latin).
           Layout is table/block based only — DomPDF supports the CSS 2.1 subset,
           no flexbox/grid. A fixed footer repeats page numbers on every page. */

        @page { margin: 32px 38px 66px 38px; }

        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1d29; margin: 0; }

        /* ---- Letterhead ---- */
        .letterhead { border-bottom: 3px solid #1e3a5f; padding-bottom: 10px; margin-bottom: 2px; }
        .letterhead table { width: 100%; border-collapse: collapse; }
        .letterhead td { border: none; padding: 0; vertical-align: top; }
        .lh-logo { width: 70px; }
        .lh-logo img { max-height: 58px; max-width: 64px; }
        .company-name { font-size: 19px; font-weight: bold; color: #1e3a5f; line-height: 1.15; }
        .company-legal { font-weight: normal; font-size: 10.5px; color: #5a6472; }
        .company-contact { font-size: 10px; color: #4a5464; margin-top: 3px; line-height: 1.45; }
        .company-bin { font-size: 10px; color: #1e3a5f; font-weight: bold; margin-top: 2px; }

        /* ---- Document title band ---- */
        .doc-band { text-align: center; margin: 13px 0 3px; }
        .doc-title {
            display: inline-block; font-size: 13.5px; font-weight: bold; color: #1a1d29;
            text-transform: uppercase; letter-spacing: 1px;
            border: 1.3px solid #1e3a5f; border-radius: 3px; padding: 4px 22px; background: #f4f7fb;
        }
        .doc-period { font-size: 10.5px; color: #4a5464; margin-top: 6px; }

        /* ---- Section subheads ---- */
        h3 {
            font-size: 12px; margin: 16px 0 4px; color: #1e3a5f;
            text-transform: uppercase; letter-spacing: .5px;
            border-bottom: 1px solid #d5dde6; padding-bottom: 3px;
        }

        /* ---- Data tables ---- */
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        thead { display: table-header-group; }   /* repeat column headers across pages */
        th, td { text-align: left; padding: 5px 7px; border: 1px solid #dce3ea; }
        thead th {
            background: #1e3a5f; color: #fff; font-weight: bold; font-size: 10px;
            text-transform: uppercase; letter-spacing: .3px; border: 1px solid #1e3a5f;
        }
        tbody tr:nth-child(even) td { background: #f6f9fc; }
        td.num, th.num { text-align: right; }
        tr.total td { font-weight: bold; border-top: 2px solid #1e3a5f; border-bottom: 2px solid #1e3a5f; background: #eef3f9; }

        /* ---- Fixed footer (repeats on every page) ---- */
        .pdf-footer {
            position: fixed; bottom: -48px; left: 0; right: 0; height: 40px;
            border-top: 1px solid #d5dde6; padding-top: 5px;
            font-size: 8.5px; color: #8a94a2;
        }
        .pdf-footer table { width: 100%; border-collapse: collapse; margin: 0; }
        .pdf-footer td { border: none; padding: 0; font-size: 8.5px; color: #8a94a2; }
        .pdf-footer .note { font-style: italic; color: #5a6472; }
        .pdf-footer .center { text-align: center; }
        .pdf-footer .right { text-align: right; }
        .pageno:after { content: "Page " counter(page) " of " counter(pages); }
    </style>
</head>
<body>
    @php($setting = $setting ?? null)

    <div class="pdf-footer">
        <table>
            <tr>
                <td class="note">Computer-generated report</td>
                <td class="center pageno"></td>
                <td class="right">Generated {{ now()->format('d M Y, h:i A') }}</td>
            </tr>
        </table>
    </div>

    <div class="letterhead">
        <table>
            <tr>
                @if ($setting?->logoDataUri())
                    <td class="lh-logo"><img src="{{ $setting->logoDataUri() }}" alt="logo"></td>
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
        <div class="doc-period">@yield('period')</div>
    </div>

    @yield('content')

    @if ($setting?->footer_note)
        <div style="margin-top: 22px; text-align: center; font-size: 9.5px; color: #5a6472; font-style: italic;">
            {{ $setting->footer_note }}
        </div>
    @endif
</body>
</html>

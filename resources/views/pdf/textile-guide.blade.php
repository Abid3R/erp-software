@extends('pdf.layout')

@php
    $tk = config('erp.currency.pdf_symbol', 'Tk ');
@endphp

@section('title', 'Textile Module — Step-by-Step Guide')
@section('period', 'A worked, testable example (company: DEMO)')

@section('content')
    <p style="margin: 4px 0 10px; font-size: 11px;">
        This guide walks the whole textile flow — <strong>knitting &rarr; dyeing &rarr; finishing &rarr; sell</strong> —
        one step at a time, with real example values. Every figure below is produced by the built-in demo,
        so you can open each screen in the <strong>DEMO</strong> company and see the exact same numbers. The same
        command also loads <strong>~10 hand-test records in every module</strong>, spread across their stages (see Part H):
        <span style="font-family: 'DejaVu Sans Mono', monospace; background:#f0f4f8; padding:1px 4px; border:1px solid #dce3ea;">php artisan db:seed --class="Database\Seeders\DemoWalkthroughSeeder"</span>
    </p>

    <h3>The big picture</h3>
    <table>
        <thead>
            <tr><th style="width:22%">Stage</th><th>What happens</th></tr>
        </thead>
        <tbody>
            <tr><td>Master data (once)</td><td>Set up process types, machines, the <strong>fabric specification</strong> (with its material recipe) and <strong>lab dip</strong> (colour recipe).</td></tr>
            <tr><td>Plan</td><td>Turn a customer order into a <strong>Production Plan</strong> (Knit &rarr; Dye &rarr; Finish) with dates.</td></tr>
            <tr><td>Knit</td><td>In-house or sub-contract. Yarn &rarr; grey fabric. Materials auto-calculated from the recipe.</td></tr>
            <tr><td>Dye</td><td>Grey + dyes + chemicals &rarr; dyed fabric, against an approved lab dip. Chemicals auto-calculated.</td></tr>
            <tr><td>Finish</td><td>Dyed &rarr; finished fabric. Sold to the customer.</td></tr>
        </tbody>
    </table>
    <p style="margin: 6px 0 0; font-size: 10.5px; color: #4a5464;">
        Everything is under the <strong>Textile</strong> menu. Stock and accounts update automatically at each step.
    </p>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part A &mdash; Master data (set up once)</h3>

    <p style="margin: 4px 0 4px; font-size: 11px;"><strong>A1. Fabric specification &amp; recipe.</strong>
        Textile &rarr; Fabric Specifications &rarr; New.</p>
    <table>
        <thead><tr><th style="width:6%">Step</th><th style="width:40%">Where</th><th>Example values</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Pick the product</td><td><strong>Grey Fabric (Single Jersey)</strong></td></tr>
            <tr><td>2</td><td>Fabric fields</td><td>80% Cotton 20% Polyester &middot; 280/290 GSM &middot; 72" Open &middot; Grey</td></tr>
            <tr><td>3</td><td>Knitting parameters</td><td>Machine dia <strong>30</strong> &middot; Gauge <strong>24</strong> &middot; Stitch length <strong>4.1+5.0+0.5</strong> &middot; Yarn 30s &middot; Combed</td></tr>
            <tr><td>4</td><td>Material consumption</td><td>Cotton Yarn 30s &mdash; <strong>1.0 per unit</strong>, wastage <strong>5%</strong> (knitting loss)</td></tr>
        </tbody>
    </table>
    <p style="margin: 4px 0 8px; font-size: 10.5px; color:#4a5464;">
        Result: picking this fabric on a knitting order auto-fills all these fields, and its material need is
        calculated from the recipe. Print it with <em>Spec sheet</em> to hand to the machine operator.</p>

    <p style="margin: 4px 0 4px; font-size: 11px;"><strong>A2. Lab dip (colour recipe).</strong>
        Textile &rarr; Lab Dips &rarr; New, then approve it (Submit &rarr; Send to lab &rarr; Internal approve &rarr; Customer approved).</p>
    <table>
        <thead><tr><th style="width:6%">Step</th><th style="width:40%">Where</th><th>Example values</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Colour</td><td>Navy Blue &middot; Pantone 19-3832</td></tr>
            <tr><td>2</td><td>Dye-house parameters</td><td>Reactive &middot; liquor ratio <strong>1:8</strong> &middot; 60&deg;C &middot; shade <strong>3%</strong></td></tr>
            <tr><td>3</td><td>Dyeing recipe</td><td>Reactive Dye <strong>3% owf</strong> &middot; Salt <strong>40 g/L</strong> &middot; Soda Ash <strong>15 g/L</strong></td></tr>
            <tr><td>4</td><td>Approve</td><td>Only an approved lab dip can be used on a dyeing order. Print with <em>Recipe sheet</em>.</td></tr>
        </tbody>
    </table>

    <p style="margin: 6px 0 4px; font-size: 11px;"><strong>A3. Dyeing specification (standard dyeing program).</strong>
        Textile &rarr; Dyeing Specifications &rarr; New. The per-fabric dyeing standard (the parallel of the fabric
        spec, for the dye house): pick the dyed-fabric product, set the process parameters (process, liquor ratio,
        temperature, shade, fastness) and the recipe (dyes % owf, chemicals g/L). It auto-fills a dyeing order and
        prints as a <em>Recipe sheet</em>. Roles: the <strong>dyeing specification</strong> is the standard program per
        fabric; the <strong>lab dip</strong> is the shade approval and can override it for a specific colour.</p>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part B &mdash; Master production schedule (Time &amp; Action)</h3>
    <p style="margin: 4px 0 4px; font-size: 11px;">The plan is the master schedule for an order. It carries the
        full specification a planner works from, in four blocks:</p>
    <table>
        <thead><tr><th style="width:26%">Block</th><th>Fields</th></tr></thead>
        <tbody>
            <tr><td>Order &amp; buyer</td><td>Buyer, Style/Article no, Buyer PO no, Order qty + unit (e.g. 1,200 PCS), Colour</td></tr>
            <tr><td>Fabric specification</td><td>Composition, GSM, Width/Dia, Fabric type, Production qty (KG)</td></tr>
            <tr><td>Schedule (T&amp;A)</td><td>Booking, Plan, Start, Delivery/Due, Shipment dates</td></tr>
            <tr><td>Stages</td><td>Knitting &rarr; Dyeing &rarr; Finishing, each with machine + planned start/end + qty</td></tr>
        </tbody>
    </table>
    <table>
        <thead><tr><th style="width:6%">Step</th><th style="width:40%">Where</th><th>Example &amp; what happens</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Sales &rarr; Sales Orders &rarr; (confirmed order) &rarr; <em>Production plan</em></td><td>Creates <strong>PLAN-0002</strong>; buyer, colour and fabric spec auto-fill from the product's specification; stages <strong>Knitting &rarr; Dyeing &rarr; Finishing</strong> dated to the delivery date.</td></tr>
            <tr><td>2</td><td>Textile &rarr; Production Plans &rarr; open it</td><td>Fill buyer/style/PO, adjust dates &amp; machines per stage. Progress shows 0% until runs are recorded, then rolls up automatically.</td></tr>
            <tr><td>3</td><td>On a stage &rarr; <em>Generate process order</em></td><td>Creates the actual run for that stage, linked back to the plan and the order.</td></tr>
            <tr><td>4</td><td><em>Plan sheet</em></td><td>Prints the whole schedule (all specs + dated stages) to hand to the floor.</td></tr>
        </tbody>
    </table>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part C &mdash; In-house knitting</h3>
    <table>
        <thead><tr><th style="width:6%">Step</th><th style="width:40%">Where</th><th>Example &amp; what happens</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Textile &rarr; Process Orders &rarr; New</td><td>Process = Knitting; output = Grey Fabric (spec auto-fills); planned <strong>480 kg</strong>.</td></tr>
            <tr><td>2</td><td>Inputs &rarr; <em>Calculate from recipe</em></td><td>Fills Cotton Yarn = 480 &times; 1.0 &times; 1.05 = <strong>504 kg</strong> (incl. 5% loss).</td></tr>
            <tr><td>3</td><td><em>Issue materials</em></td><td>Yarn leaves stock into Work-in-Progress (WIP).</td></tr>
            <tr><td>4</td><td><em>Add costs</em></td><td>Labour 6,000 &middot; machine 12 h &middot; utility 1,500 &middot; overhead 1,000 &mdash; capitalised into WIP.</td></tr>
            <tr><td>5</td><td><em>Record production</em></td><td>Produced 480, wastage 20 &mdash; a tracked grey-fabric batch is received at true cost.</td></tr>
            <tr><td>6</td><td><em>Record QC</em></td><td>Passed 470 / rejected 10 &mdash; rejects removed from stock; order Completed.</td></tr>
        </tbody>
    </table>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part D &mdash; Knitting sub-contract (job-work)</h3>
    <table>
        <thead><tr><th style="width:6%">Step</th><th style="width:40%">Where</th><th>Example &amp; what happens</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Textile &rarr; Knitting Sub-contracts &rarr; New</td><td>Sub-contractor = <strong>Acme Knitting Mills</strong>; output = Grey Fabric; service item = Service Charge for Knitting.</td></tr>
            <tr><td>2</td><td>Service charge</td><td>Bill qty <strong>2,000 KG</strong> &times; rate <strong>25</strong> = {{ $tk }}<strong>50,000</strong> (BDT).</td></tr>
            <tr><td>3</td><td><em>Issue yarn</em></td><td>2,000 kg yarn issued to the knitter (into WIP).</td></tr>
            <tr><td>4</td><td><em>Record knitting charge</em></td><td>Posts {{ $tk }}50,000: <strong>Dr WIP / Cr Payable — Acme Knitting Mills</strong>. The mill now owes the knitter.</td></tr>
            <tr><td>5</td><td><em>Receive grey fabric</em></td><td>1,960 kg grey received at yarn + knitting cost (&asymp; {{ $tk }}280.61/kg), traced to the yarn lot.</td></tr>
            <tr><td>6</td><td>Accounts &rarr; pay the supplier</td><td>Settle Acme Knitting Mills through the normal supplier-payment flow.</td></tr>
        </tbody>
    </table>
    <p style="margin: 6px 0 0; font-size: 10.5px; color: #4a5464;">
        Print the <em>Work order</em> to hand to the sub-contractor (yarn supplied + spec + service charge).</p>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part E &mdash; Dyeing (with the lab-dip gate)</h3>
    <table>
        <thead><tr><th style="width:6%">Step</th><th style="width:40%">Where</th><th>Example &amp; what happens</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>Textile &rarr; Process Orders &rarr; New</td><td>Process = Dyeing; output = Dyed Fabric; <strong>approved lab dip = Navy Blue</strong> (colour + recipe auto-fill); planned 460 kg.</td></tr>
            <tr><td>2</td><td>Inputs &rarr; add grey fabric, then <em>Calculate from recipe</em></td><td>Adds the chemicals for 460 kg at liquor 1:8 (see Part G).</td></tr>
            <tr><td>3</td><td>Issue &rarr; Add costs &rarr; Produce &rarr; QC</td><td>Same flow as knitting; dyeing cannot start without the approved lab dip.</td></tr>
        </tbody>
    </table>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part F &mdash; Finishing &amp; sale</h3>
    <p style="margin: 4px 0 0; font-size: 11px;">
        Run a <strong>Compacting/Finishing</strong> process order (dyed &rarr; finished fabric), then the finished fabric feeds
        the customer sales order. Open any finished batch and click <em>Trace</em> to follow it all the way back to the
        exact yarn lot.</p>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part G &mdash; Material requirement: how the quantities are calculated</h3>
    <p style="margin: 4px 0 4px; font-size: 11px;">The recipe holds the rate <em>per unit of output</em>; the order
        multiplies it by the quantity and adds wastage. Three dosing bases (the Bangladesh conventions):</p>
    <table>
        <thead><tr><th>Basis</th><th>Formula</th><th class="num">Example (order shown)</th></tr></thead>
        <tbody>
            <tr><td>Per unit (yarn)</td><td>qty &times; rate &times; (1 + wastage)</td><td class="num">400 &times; 1.0 &times; 1.05 = <strong>420 kg</strong> yarn</td></tr>
            <tr><td>% owf (dye)</td><td>qty &times; rate &divide; 100</td><td class="num">460 &times; 3% = <strong>13.8 kg</strong> dye</td></tr>
            <tr><td>g/L (salt)</td><td>(qty &times; liquor) &times; rate &divide; 1000</td><td class="num">460 &times; 8 &times; 40 &divide; 1000 = <strong>147.2 kg</strong> salt</td></tr>
            <tr><td>g/L (soda)</td><td>(qty &times; liquor) &times; rate &divide; 1000</td><td class="num">460 &times; 8 &times; 15 &divide; 1000 = <strong>55.2 kg</strong> soda</td></tr>
        </tbody>
    </table>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Printable documents (hand to the floor)</h3>
    <table>
        <thead><tr><th style="width:34%">Document</th><th>Where</th></tr></thead>
        <tbody>
            <tr><td>Fabric spec sheet</td><td>Textile &rarr; Fabric Specifications &rarr; Spec sheet</td></tr>
            <tr><td>Dyeing specification sheet</td><td>Textile &rarr; Dyeing Specifications &rarr; Recipe sheet</td></tr>
            <tr><td>Lab-dip recipe sheet</td><td>Textile &rarr; Lab Dips &rarr; Recipe sheet</td></tr>
            <tr><td>Knitting job card</td><td>Textile &rarr; Process Orders &rarr; Job card</td></tr>
            <tr><td>Sub-contract work order</td><td>Textile &rarr; Knitting Sub-contracts &rarr; Work order</td></tr>
            <tr><td>Production plan (T&amp;A) sheet</td><td>Textile &rarr; Production Plans &rarr; Plan sheet</td></tr>
        </tbody>
    </table>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part H &mdash; Ready-made records to test by hand</h3>
    <p style="margin: 4px 0 4px; font-size: 11px;">The demo also loads about <strong>10 records in every module</strong>,
        spread across their stages, so you can click each stage-specific button. Look for records marked
        <strong>TC&hellip;</strong> or noted <strong>TESTCASE</strong>.</p>
    <table>
        <thead><tr><th style="width:30%">Module</th><th class="num">Count</th><th>Stages you can test</th></tr></thead>
        <tbody>
            <tr><td>Fabric Specifications</td><td class="num">10</td><td>10 fabrics with recipe &mdash; spec sheet &amp; auto-fill</td></tr>
            <tr><td>Dyeing Specifications</td><td class="num">10</td><td>10 dyed fabrics with dye/chemical recipe; 5 approved with recipe no + version</td></tr>
            <tr><td>Process Routes</td><td class="num">3</td><td>Knit→Dye→Finish, Knit→Finish, Dye→Compact (drive plan generation)</td></tr>
            <tr><td>Fabric Rolls</td><td class="num">3</td><td>Rolls generated on a completed run &mdash; pass/fail QC per roll</td></tr>
            <tr><td>Rework runs</td><td class="num">2</td><td>QC-reject runs each with a linked rework order</td></tr>
            <tr><td>Lab Dips</td><td class="num">10</td><td>Draft, Submitted, In-lab, Internal approved, Sent to customer, Customer approved, Rejected (+ Cancel action)</td></tr>
            <tr><td>Process Orders</td><td class="num">10</td><td>Draft, Planned (&rarr; Issue), In-progress (&rarr; Costs/Produce), QC (&rarr; Record QC), Completed, Cancelled</td></tr>
            <tr><td>Knitting Sub-contracts</td><td class="num">10</td><td>Draft, Planned, In-progress (&rarr; Record charge), Charged (&rarr; Receive fabric), QC, Completed, Cancelled</td></tr>
            <tr><td>Production Plans</td><td class="num">10</td><td>Draft, Scheduled, In-progress, On-hold, Completed, Cancelled &mdash; each with buyer/style specs + stages</td></tr>
        </tbody>
    </table>
    <p style="margin: 6px 0 0; font-size: 10.5px; color: #4a5464;">
        Re-load the whole textile demo anytime with
        <span style="font-family: 'DejaVu Sans Mono', monospace; background:#f0f4f8; padding:1px 3px; border:1px solid #dce3ea;">php artisan db:seed --class="Database\Seeders\DemoWalkthroughSeeder"</span>
        &mdash; it is idempotent (won't duplicate).</p>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Part I &mdash; Integrated ERP features (advanced)</h3>
    <p style="margin: 4px 0 4px; font-size: 11px;">The textile module is an extension of the core ERP &mdash;
        it shares the same products, inventory, accounts, MRP, QC and approvals. These features connect it end to end:</p>
    <table>
        <thead><tr><th style="width:26%">Feature</th><th>What it does &amp; where</th></tr></thead>
        <tbody>
            <tr><td>Textile product fields</td><td>Flag a product as textile (yarn/grey/dyed/finished) with GSM, width, colour, roll-tracked &mdash; on the Product form. Normal products are unaffected.</td></tr>
            <tr><td>Wastage (expected vs actual)</td><td>Default wastage % per product &amp; per process; each order shows expected output and, after production, actual wastage % &amp; reason. See the Wastage report's Exp% / Act% columns.</td></tr>
            <tr><td>Process routes</td><td>Textile &rarr; Process Routes &mdash; define a product's production sequence once; plans generated for that product follow the route.</td></tr>
            <tr><td>Fabric rolls</td><td>Roll-tracked products split production into rolls (Textile &rarr; Fabric Rolls) with per-roll weight/GSM/QC; Roll Traceability report ties each roll to its batch and source materials.</td></tr>
            <tr><td>Rework</td><td>On a completed run with QC rejects, <em>Create rework</em> raises a linked run (the original is never changed). Rework report lists them with added cost.</td></tr>
            <tr><td>Versioned recipes</td><td>Dyeing/fabric specs carry recipe no + version + approval; recipes auto-scale to the order quantity. <em>Approve</em> to lock a version.</td></tr>
            <tr><td>MRP &rarr; purchasing</td><td>Reports &rarr; MRP &rarr; <em>Create purchase requisition</em> turns shortages into a draft requisition in the normal purchasing flow.</td></tr>
            <tr><td>Textile dashboard KPIs</td><td>Dashboard shows yarn/grey/dyed/finished stock, WIP, wastage %, pending QC, active runs and material shortages &mdash; live values.</td></tr>
        </tbody>
    </table>

    {{-- ------------------------------------------------------------------ --}}
    <h3>Test it yourself &mdash; 6-point checklist</h3>
    <table>
        <thead><tr><th style="width:6%">#</th><th>Check</th><th>Expected</th></tr></thead>
        <tbody>
            <tr><td>1</td><td>New knitting order &rarr; pick Grey Fabric</td><td>Composition, GSM, dia 30, gauge 24, stitch length auto-fill.</td></tr>
            <tr><td>2</td><td>Set qty 400 &rarr; <em>Calculate from recipe</em></td><td>Cotton Yarn = 420 kg.</td></tr>
            <tr><td>3</td><td>New dyeing order &rarr; pick Navy lab dip, qty 460 &rarr; Calculate</td><td>Dye 13.8 &middot; Salt 147.2 &middot; Soda 55.2 kg.</td></tr>
            <tr><td>4</td><td>Knitting Sub-contracts &rarr; KNIT-0004</td><td>Charge {{ $tk }}50,000; grey received 1,960 kg.</td></tr>
            <tr><td>5</td><td>Production Plans &rarr; PLAN-0002</td><td>Stages Knit &rarr; Dye &rarr; Finish.</td></tr>
            <tr><td>6</td><td>Batches &rarr; open finished &rarr; <em>Trace</em></td><td>Traces back to the yarn lot.</td></tr>
        </tbody>
    </table>
    <p style="margin: 8px 0 0; font-size: 10.5px; color: #4a5464;">
        Every value above is live demo data in the DEMO company &mdash; open the screen and confirm it matches.</p>
@endsection

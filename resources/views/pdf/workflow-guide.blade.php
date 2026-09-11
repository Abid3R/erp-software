@extends('pdf.layout')

@php
    $tk = config('erp.currency.pdf_symbol', 'Tk ');
@endphp

@section('title', 'ERP Workflow Guide')
@section('period', 'A worked example for management review')

@section('content')
    <p style="margin: 4px 0 10px; font-size: 11px;">
        This ERP runs the whole business as one connected system: a document flows into the next, and
        stock and accounts update automatically. Below is the document flow of each area, followed by two
        worked examples you can create live in the system (company: <strong>DEMO</strong>). Every figure
        shown is real data produced by the built-in demo.
    </p>

    <h3>The document flow</h3>
    <table>
        <thead>
            <tr><th style="width:20%">Area</th><th>Flow</th></tr>
        </thead>
        <tbody>
            <tr><td>Local sale</td><td>Customer &rarr; Quotation &rarr; Sales Order &rarr; Delivery Order &rarr; Sales Invoice &rarr; Payment</td></tr>
            <tr><td>Export sale</td><td>Sales Order &rarr; Proforma Invoice &rarr; Letter of Credit &rarr; Delivery Order &rarr; Commercial Invoice &rarr; Packing List &rarr; Shipment &rarr; Payment</td></tr>
            <tr><td>Purchase</td><td>Requisition &rarr; RFQ &rarr; Compare &amp; Award &rarr; Purchase Order &rarr; Goods Receipt &rarr; Supplier Invoice &rarr; Payment</td></tr>
            <tr><td>Manufacturing</td><td>Yarn &rarr; Knitting &rarr; Lab-dip approval &rarr; Dyeing &rarr; Finishing &rarr; Quality Control &rarr; Finished Goods</td></tr>
        </tbody>
    </table>

    <h3>Worked example 1 &mdash; Export sale under a Letter of Credit</h3>
    <table>
        <thead>
            <tr>
                <th style="width:6%">Step</th>
                <th style="width:38%">Where to click</th>
                <th>Example values &amp; what the system does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Export &rarr; Letters of Credit &rarr; New</td>
                <td>Amount USD 100,000 at rate 110; issuing bank Citibank; expiry +3 months. Creates <strong>LC-0001</strong>.</td>
            </tr>
            <tr>
                <td>2</td>
                <td>Export &rarr; Proforma Invoices &rarr; New, then <em>Approve</em>, then <em>Allocate to LC</em></td>
                <td>PI-0001 = 300 kg &times; USD 200 = <strong>USD 60,000</strong>, allocated to LC-0001. A second PI-0002 (USD 25,000) leaves <strong>USD 15,000 remaining</strong> on the LC.</td>
            </tr>
            <tr>
                <td>3</td>
                <td>On the approved PI &rarr; <em>Create commercial invoice</em></td>
                <td>A draft commercial invoice is built automatically, carrying the customer, currency and lines &mdash; no re-typing.</td>
            </tr>
            <tr>
                <td>4</td>
                <td>Export &rarr; Commercial Invoices &rarr; <em>Approve</em> &rarr; <em>Post AR</em></td>
                <td>Books the receivable in local currency: 60,000 &times; 110 = <strong>{{ $tk }}6,600,000</strong> (Dr Receivable / Cr Sales). The invoice is USD; the books stay in Taka.</td>
            </tr>
            <tr>
                <td>5</td>
                <td>On the invoice &rarr; <em>Generate packing list</em></td>
                <td>Creates <strong>PL-0001</strong> with the invoice lines ready for carton/roll weights.</td>
            </tr>
            <tr>
                <td>6</td>
                <td>Export &rarr; Shipments &rarr; New, then <em>Update status</em></td>
                <td>Add vessel, container and BL/AWB; advance Draft &rarr; Ready &rarr; <strong>Shipped</strong>. Links the whole chain together.</td>
            </tr>
            <tr>
                <td>7</td>
                <td>Payments &rarr; record a customer receipt</td>
                <td>Money received reduces the customer's outstanding balance.</td>
            </tr>
        </tbody>
    </table>
    <p style="margin: 6px 0 0; font-size: 10.5px; color: #4a5464;">
        <strong>Built-in safety:</strong> allocating a PI beyond the LC balance is blocked; posting the same
        invoice twice is refused; a USD invoice always posts to the Taka ledger at its exchange rate.
    </p>

    <h3>Worked example 2 &mdash; Making the fabric (manufacturing)</h3>
    <table>
        <thead>
            <tr>
                <th style="width:6%">Step</th>
                <th style="width:38%">Where to click</th>
                <th>Example values &amp; what the system does</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>Manufacturing &rarr; Process Orders &rarr; New</td>
                <td>Process = Knitting; output = Grey Fabric; planned 480 kg; input = Cotton Yarn 500 kg.</td>
            </tr>
            <tr>
                <td>2</td>
                <td>On the order &rarr; <em>Issue materials</em></td>
                <td>The yarn leaves stock and its value moves into Work-in-Progress (WIP).</td>
            </tr>
            <tr>
                <td>3</td>
                <td>On the order &rarr; <em>Add costs</em></td>
                <td>Labour 6,000, machine hours 12, utility 1,500, overhead 1,000 &mdash; added on top of the yarn cost.</td>
            </tr>
            <tr>
                <td>4</td>
                <td>On the order &rarr; <em>Record production</em></td>
                <td>Produced 480, wastage 20. A new tracked batch of Grey Fabric is received into stock at its true cost.</td>
            </tr>
            <tr>
                <td>5</td>
                <td>On the order &rarr; <em>Record QC</em></td>
                <td>Inspected 480, passed 470, rejected 10. The 10 rejects are removed from sellable stock; order Completed.</td>
            </tr>
            <tr>
                <td>6</td>
                <td>Repeat for Dyeing &amp; Finishing (Dyeing needs an approved Lab Dip)</td>
                <td>Grey &rarr; Dyed &rarr; Finished Fabric &mdash; the finished fabric then feeds the export sale above.</td>
            </tr>
        </tbody>
    </table>
    <p style="margin: 6px 0 0; font-size: 10.5px; color: #4a5464;">
        <strong>Traceability:</strong> open any finished batch and click <em>Trace</em> to follow it all the way back to the exact yarn lot it came from.
    </p>

    <h3>Load this exact demo in one command</h3>
    <p style="margin: 4px 0 0; font-size: 11px;">
        Run <span style="font-family: 'DejaVu Sans Mono', monospace; background:#f0f4f8; padding:1px 4px; border:1px solid #dce3ea;">php artisan db:seed --class="Database\Seeders\DemoWalkthroughSeeder"</span>
        and every screen and report above fills with this reconciled example in the DEMO company.
    </p>
@endsection

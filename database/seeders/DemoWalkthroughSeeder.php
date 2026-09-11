<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One-command demo for a management walkthrough. Runs the textile manufacturing
 * demo and the export-sales demo (both idempotent) so the DEMO company shows a
 * complete, reconciled story — yarn bought and knitted/dyed/finished into fabric,
 * then sold for export under a letter of credit — and prints exactly where to look
 * in the app.
 *
 *   php artisan db:seed --class="Database\Seeders\DemoWalkthroughSeeder"
 */
class DemoWalkthroughSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TextileDemoSeeder::class,
            ExportDemoSeeder::class,
            TextileTestCasesSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('====================================================================');
        $this->command->info('  DEMO READY — log in and open these screens (company: DEMO)');
        $this->command->info('====================================================================');

        $this->command->table(
            ['Where to look', 'What you will see'],
            [
                ['Dashboard', 'Export KPIs: active LCs, LC total/utilised/remaining, receivables'],
                ['Manufacturing > Process Orders', 'KNIT / DYE / COMP runs, all completed'],
                ['Manufacturing > Batches > (open) > Trace', 'Finished fabric traced back to the yarn lot'],
                ['Manufacturing > Quality Inspections', 'QC records with passed / rejected quantities'],
                ['Export > Letters of Credit > LC-0001', 'USD 100,000: 85,000 allocated, 15,000 remaining'],
                ['Export > Proforma Invoices', 'PI-0001 (USD 60,000) & PI-0002 (USD 25,000), on the LC'],
                ['Export > Commercial Invoices > CI-0001', 'Posted: Tk 6,600,000 receivable (60,000 x 110)'],
                ['Export > Packing Lists / Shipments', 'PL-0001 and a Shipped consignment'],
                ['Reports > All Reports > Export', 'LC Utilization, Order->PI->LC->Shipment, and more'],
                ['Reports > All Reports > Manufacturing', 'Production Costing, Wastage, Machine Performance'],
            ],
        );

        $this->command->newLine();
        $this->command->info('Tip: a printable one-page walkthrough is in docs/ERP-Workflow-Guide.pdf');
    }
}

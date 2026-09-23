<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * One-command demo for a management walkthrough. Runs the two consolidated textile
 * seeders (both idempotent) so the DEMO company shows a complete, reconciled story —
 * yarn bought and knitted, dyed via one-part AND two-part programmes, finished into
 * fabric, then sold for export under a letter of credit — and prints exactly where
 * to look in the app. The core ERP (including the HR module) is seeded by
 * {@see DatabaseSeeder}; this only layers the textile/export demo on top.
 *
 *   php artisan db:seed --class="Database\Seeders\DemoWalkthroughSeeder"
 */
class DemoWalkthroughSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TextileMasterDataSeeder::class,
            TextileWorkflowSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('====================================================================');
        $this->command->info('  DEMO READY — log in and open these screens (company: DEMO)');
        $this->command->info('====================================================================');

        $this->command->table(
            ['Where to look', 'What you will see'],
            [
                ['Dashboard', 'Export KPIs: active LCs, LC total/utilised/remaining, receivables'],
                ['Textile > Process Orders', 'One-part (Knit→Pretreat→Dye→Wash-off→Finish) & two-part (…Dye 1→Inter. Wash→Dye 2…) runs, completed'],
                ['Textile > Lab Dips', 'Navy = One-Part, Teal = Two-Part (both customer-approved)'],
                ['Manufacturing > Batches > (open) > Trace', 'Finished fabric traced back to the yarn lot'],
                ['Manufacturing > Quality Inspections', 'QC records with passed / rejected quantities + a rework'],
                ['Textile > Production Plans', 'SO-TEX-1 plan: dyeing phase expanded into one-part & two-part sub-steps'],
                ['Export > Letters of Credit > (open)', 'USD 100,000: 85,000 allocated, 15,000 remaining'],
                ['Export > Commercial Invoices', 'Posted AR + packing list + a Shipped consignment'],
                ['Reports > All Reports', 'Export & Manufacturing reports (costing, wastage, machine performance)'],
            ],
        );

        $this->command->newLine();
        $this->command->info('Tip: a printable one-page walkthrough is in docs/ERP-Workflow-Guide.pdf');
    }
}

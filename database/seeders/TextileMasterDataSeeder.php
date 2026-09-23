<?php

namespace Database\Seeders;

use App\Enums\DyeingType;
use App\Enums\LabDipStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\DyeingSpecification;
use App\Models\LabDip;
use App\Models\Machine;
use App\Models\ProcessRoute;
use App\Models\ProcessType;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\Unit;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;

/**
 * Seeder ① of two — TEXTILE MASTER DATA (reference / configuration only; no
 * inventory or ledger movements). Establishes every "thing you set up once" that
 * the textile module needs, so the companion {@see TextileWorkflowSeeder} can run
 * every process end to end against real masters:
 *
 *   - Units + textile products across the chain (yarn, dyes/chemicals, grey,
 *     dyed and finished fabric) flagged so the Textile dashboard classifies stock.
 *   - Machines for each process (knit / dye / finish).
 *   - Fabric specifications (with yarn consumption recipes) — auto-fill knitting.
 *   - Dyeing specifications (with dye + chemical recipes).
 *   - Lab dips carrying an approved ONE-PART and an approved TWO-PART recipe, so
 *     the production plan expands the dyeing phase into its real sub-steps.
 *   - A configurable process route (Knit → Dye → Finish).
 *
 * Idempotent (updateOrCreate throughout). Runs inside the DEMO company context so
 * company_id is stamped and scoped lookups resolve. HR and the rest of the core
 * ERP are seeded by {@see DatabaseSeeder} and are untouched here.
 */
class TextileMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('code', 'DEMO')->first();
        if ($company === null) {
            $this->command->error('Demo company (DEMO) not found. Run the main DatabaseSeeder first.');

            return;
        }

        app(CompanyContext::class)->runFor($company, function (): void {
            $kg = Unit::query()->where('code', 'KG')->first()
                ?? Unit::create(['name' => 'Kilogram', 'code' => 'KG', 'factor' => 1]);

            $this->products($kg);
            $this->machines();
            $this->processTypes();
            $this->fabricSpecifications();
            $this->dyeingSpecifications();
            $this->labDips();
            $this->processRoute();

            $this->command->info('Textile master data ready: products, machines, fabric & dyeing specs, one-part + two-part lab dips, and a process route.');
        });
    }

    /**
     * Textile products across the chain. Raw materials carry a cost; grey/dyed/
     * finished are produced (cost accrues from process runs). Textile flags drive
     * the dashboard stage KPIs; grey & finished are roll-tracked.
     */
    private function products(Unit $kg): void
    {
        $mk = fn (string $sku, string $name, float $cost, float $sell, array $extra = []): Product => Product::updateOrCreate(
            ['sku' => $sku],
            array_merge(['unit_id' => $kg->getKey(), 'name' => $name, 'cost_price' => $cost, 'selling_price' => $sell, 'is_active' => true], $extra),
        );

        // Raw materials (dyes/chemicals reused by both one-part and two-part recipes).
        $mk('RM-YARN', 'Cotton Yarn 30s', 250, 0, ['is_textile' => true, 'textile_type' => 'yarn']);
        $mk('RM-DYE', 'Reactive Dye', 800, 0);
        $mk('RM-CHEM', 'Dyeing Chemical', 150, 0);
        $mk('RM-SALT', 'Salt (Glauber)', 15, 0);
        $mk('RM-SODA', 'Soda Ash', 60, 0);
        $mk('RM-PEROX', 'Hydrogen Peroxide', 90, 0);      // pretreatment
        $mk('RM-DYE2', 'Reactive Dye (2nd bath)', 850, 0); // two-part second shot

        // Semi-finished / finished — ONE-PART (Navy) chain: Single Jersey.
        $mk('SF-GREY', 'Grey Fabric (Single Jersey)', 0, 0, [
            'is_textile' => true, 'textile_type' => 'grey_fabric', 'is_roll_tracked' => true,
            'fabric_type' => 'Single Jersey', 'construction' => '80% Cotton 20% Polyester',
            'gsm' => '160', 'width' => '72 Inch Open', 'colour' => 'Grey',
        ]);
        $mk('SF-DYED', 'Dyed Fabric (Navy)', 0, 0, [
            'is_textile' => true, 'textile_type' => 'dyed_fabric',
            'fabric_type' => 'Single Jersey', 'construction' => '80% Cotton 20% Polyester',
            'gsm' => '160', 'width' => '72 Inch Open', 'colour' => 'Navy Blue',
        ]);
        $mk('FG-FAB', 'Finished Fabric (Navy, Compacted)', 480, 620, [
            'is_textile' => true, 'textile_type' => 'finished_fabric', 'is_roll_tracked' => true,
            'fabric_type' => 'Single Jersey', 'construction' => '80% Cotton 20% Polyester',
            'gsm' => '160', 'width' => '72 Inch Open', 'colour' => 'Navy Blue', 'style' => 'STYLE-SJ-NAVY',
        ]);

        // Semi-finished / finished — TWO-PART (Teal) chain: Fleece.
        $mk('SF-GREY-FL', 'Grey Fabric (Fleece)', 0, 0, [
            'is_textile' => true, 'textile_type' => 'grey_fabric', 'is_roll_tracked' => true,
            'fabric_type' => 'Fleece', 'construction' => '100% Cotton', 'gsm' => '320', 'width' => '68 Inch Open', 'colour' => 'Grey',
        ]);
        $mk('FG-FAB-TEAL', 'Finished Fabric (Teal Fleece)', 520, 690, [
            'is_textile' => true, 'textile_type' => 'finished_fabric', 'is_roll_tracked' => true,
            'fabric_type' => 'Fleece', 'construction' => '100% Cotton', 'gsm' => '320', 'width' => '68 Inch Open',
            'colour' => 'Teal', 'style' => 'STYLE-FL-TEAL',
        ]);

        // Knitting service item for the sub-contract (job-work) run.
        $mk('NS-KNIT', 'Service Charge for Knitting', 0, 0, ['is_service' => true]);
    }

    private function machines(): void
    {
        foreach ([
            ['KNIT-01', 'Circular Knitting Machine 1', 'knitting', 450],
            ['DYE-01', 'Dyeing Machine 1', 'dyeing', 800],
            ['FIN-01', 'Compacting Machine 1', 'finishing', 350],
        ] as [$code, $name, $type, $hourly]) {
            Machine::updateOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'hourly_cost' => $hourly, 'is_active' => true]);
        }
    }

    /**
     * Ensure the base process types AND the one-part / two-part dyeing SUB-STEPS
     * exist. The sub-steps are normally seeded by migration per existing company,
     * but on a fresh rebuild no company exists at migration time, so we (re)ensure
     * them here. Idempotent. DYE (main) is reused for one-part dyeing.
     */
    private function processTypes(): void
    {
        // [code, name, category, consumes, produces, requires_lab_dip, requires_qc, sort]
        $types = [
            ['KNIT', 'Knitting', 'knitting', true, true, false, true, 1],
            ['DYE', 'Dyeing', 'dyeing', true, true, true, true, 2],
            ['FINISH', 'Finishing', 'finishing', true, true, false, true, 3],
            ['WASH', 'Washing', 'finishing', true, true, false, true, 4],
            ['COMP', 'Compacting', 'finishing', true, true, false, true, 5],
            ['STEN', 'Stentering', 'finishing', true, true, false, true, 6],
            // One-part / two-part dyeing sub-steps.
            ['PRETREAT', 'Pretreatment', 'dyeing', true, true, false, false, 20],
            ['DYE1', 'Part 1 Dyeing', 'dyeing', true, true, false, true, 21],
            ['IWASH', 'Intermediate Wash', 'dyeing', true, true, false, false, 22],
            ['DYE2', 'Part 2 Dyeing', 'dyeing', true, true, false, true, 23],
            ['WASHOFF', 'Wash-off', 'finishing', true, true, false, false, 24],
        ];

        foreach ($types as [$code, $name, $category, $consumes, $produces, $labDip, $qc, $sort]) {
            ProcessType::updateOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'consumes_material' => $consumes,
                'produces_material' => $produces, 'requires_lab_dip' => $labDip, 'requires_qc' => $qc,
                'subcontractable' => $code === 'KNIT', 'sort' => $sort, 'is_active' => true,
            ]);
        }
    }

    /** Fabric specifications (auto-fill knitting orders) with a per-kg yarn recipe. */
    private function fabricSpecifications(): void
    {
        $yarn = Product::query()->where('sku', 'RM-YARN')->first();

        $specs = [
            ['SF-GREY', [
                'fabric_composition' => '80% Cotton 20% Polyester', 'gsm' => '160', 'fabric_width' => '72 Inch Open',
                'colour' => 'Grey', 'yarn_count' => '30s', 'fabric_type' => 'Single Jersey', 'quality' => 'Combed',
                'machine_diameter' => '30', 'gauge' => '24', 'stitch_length' => '4.1+5.0+0.5',
            ], 5],
            ['SF-GREY-FL', [
                'fabric_composition' => '100% Cotton', 'gsm' => '320', 'fabric_width' => '68 Inch Open',
                'colour' => 'Grey', 'yarn_count' => '24s', 'fabric_type' => 'Fleece', 'quality' => 'Combed',
                'machine_diameter' => '30', 'gauge' => '20', 'stitch_length' => '3.0+4.2',
            ], 6],
        ];

        foreach ($specs as [$sku, $attrs, $wastage]) {
            $product = Product::query()->where('sku', $sku)->first();
            if ($product === null) {
                continue;
            }
            $spec = ProductSpecification::updateOrCreate(['product_id' => $product->getKey()], $attrs);
            if ($yarn !== null) {
                $spec->consumptions()->updateOrCreate(
                    ['product_id' => $yarn->getKey()],
                    ['basis' => 'per_unit', 'rate' => 1.0, 'wastage_percent' => $wastage],
                );
            }
        }
    }

    /** Dyeing specifications with dye (% owf) + chemical (g/L) recipes. */
    private function dyeingSpecifications(): void
    {
        $dye = Product::query()->where('sku', 'RM-DYE')->first();
        $salt = Product::query()->where('sku', 'RM-SALT')->first();
        $soda = Product::query()->where('sku', 'RM-SODA')->first();

        $navy = Product::query()->where('sku', 'SF-DYED')->first();
        if ($navy !== null) {
            $spec = DyeingSpecification::updateOrCreate(
                ['product_id' => $navy->getKey()],
                [
                    'dyeing_process' => 'reactive', 'substrate' => 'Single Jersey Cotton', 'gsm' => '160',
                    'colour' => 'Navy', 'colour_ref' => 'Pantone 19-3832', 'liquor_ratio' => '1:8',
                    'temperature' => 60, 'dyeing_time' => 60, 'ph' => 11, 'shade_percentage' => 3,
                    'fastness_wash' => '4-5', 'fastness_rubbing' => '4', 'fastness_light' => '4',
                    'recipe_number' => 'DR-NAVY-01', 'version' => 1, 'approval_status' => 'approved', 'approved_at' => now(),
                ],
            );
            foreach (array_filter([[$dye, 'percent_owf', 3], [$salt, 'g_per_litre', 40], [$soda, 'g_per_litre', 15]], fn ($r) => $r[0]) as [$p, $basis, $rate]) {
                $spec->consumptions()->updateOrCreate(['product_id' => $p->getKey()], ['basis' => $basis, 'rate' => $rate, 'wastage_percent' => 0]);
            }
        }
    }

    /**
     * Approved lab dips carrying the dyeing TYPE (per the approved recipe):
     *   - Navy Blue → ONE-PART  (Pretreat → Dye → Wash-off)
     *   - Teal      → TWO-PART  (Pretreat → Dye 1 → Inter. Wash → Dye 2 → Wash-off)
     * The production plan reads these to expand the dyeing phase into real sub-steps.
     */
    private function labDips(): void
    {
        $customer = Customer::query()->where('code', 'CUST-001')->first() ?? Customer::query()->first();

        LabDip::updateOrCreate(
            ['colour' => 'Navy Blue'],
            [
                'customer_id' => $customer?->getKey(), 'colour_ref' => 'Pantone 19-3832',
                'dyeing_process' => 'reactive', 'dyeing_type' => DyeingType::OnePart,
                'substrate' => 'Single Jersey Cotton', 'gsm' => '160', 'liquor_ratio' => '1:8',
                'temperature' => 60, 'dyeing_time' => 60, 'ph' => 11, 'shade_percentage' => 3,
                'fastness_wash' => '4-5', 'fastness_rubbing' => '4', 'fastness_light' => '4',
                'recipe' => 'Reactive Navy 3% owf + salt 40 g/L + soda ash 15 g/L (single bath).',
                'recipe_version' => 'v1', 'sample_ref' => 'SMP-NAVY-01',
                'request_date' => now()->subDays(14), 'status' => LabDipStatus::CustomerApproved,
                'remarks' => 'Approved for bulk — one-part (single-bath) dyeing.',
            ],
        );

        LabDip::updateOrCreate(
            ['colour' => 'Teal'],
            [
                'customer_id' => $customer?->getKey(), 'colour_ref' => 'Pantone 18-4936',
                'dyeing_process' => 'reactive', 'dyeing_type' => DyeingType::TwoPart,
                'substrate' => 'Cotton Fleece', 'gsm' => '320', 'liquor_ratio' => '1:10',
                'temperature' => 60, 'dyeing_time' => 90, 'ph' => 11, 'shade_percentage' => 4.5,
                'fastness_wash' => '4', 'fastness_rubbing' => '3-4', 'fastness_light' => '4',
                'recipe' => 'Two-part: pretreat (scour/bleach) → dye 1 (turquoise 2.5%) → inter. wash → dye 2 (navy 2%) → wash-off.',
                'recipe_version' => 'v2', 'sample_ref' => 'SMP-TEAL-01',
                'request_date' => now()->subDays(14), 'status' => LabDipStatus::CustomerApproved,
                'remarks' => 'Approved for bulk — two-part (double-bath) dyeing for deep shade build-up.',
            ],
        );
    }

    /** A configurable Knit → Dye → Finish route on the finished navy fabric. */
    private function processRoute(): void
    {
        $product = Product::query()->where('sku', 'FG-FAB')->first();
        if ($product === null || ProcessRoute::query()->where('product_id', $product->getKey())->exists()) {
            return;
        }

        $knit = ProcessType::query()->where('code', 'KNIT')->first();
        $dye = ProcessType::query()->where('code', 'DYE')->first();
        $fin = ProcessType::query()->where('code', 'COMP')->first() ?? ProcessType::query()->where('category', 'finishing')->first();

        $route = ProcessRoute::create(['name' => 'Single Jersey — Knit → Dye → Finish', 'product_id' => $product->getKey(), 'is_active' => true]);
        $seq = 1;
        foreach (array_filter([$knit, $dye, $fin]) as $type) {
            $route->steps()->create(['process_type_id' => $type->getKey(), 'sequence' => $seq++]);
        }
    }
}

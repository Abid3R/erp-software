<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Demo departmental roles + one login per role, for showing role-based access
 * to stakeholders. Each role is scoped to the resources/report pages of a single
 * business module; each demo user gets the password "password" (demo only).
 *
 * This seeder is additive and idempotent: it never touches the admin / super_admin
 * setup created by {@see DatabaseSeeder}. Re-run any time with:
 *   php artisan db:seed --class=Database\Seeders\DemoRolesSeeder
 */
class DemoRolesSeeder extends Seeder
{
    /**
     * module role => [
     *   'resources' => resource permission suffixes (snake_case singular model),
     *   'pages'     => Shield page permission class names,
     * ]
     *
     * @var array<string, array{resources: list<string>, pages: list<string>}>
     */
    private const MODULES = [
        'sales' => [
            'resources' => ['sales::order', 'quotation', 'delivery::order', 'sales::return', 'customer'],
            'pages' => ['SalesRegister'],
        ],
        'crm' => [
            'resources' => ['lead', 'opportunity', 'customer'],
            'pages' => ['Pipeline'],
        ],
        'purchase' => [
            'resources' => [
                'purchase::requisition', 'rfq', 'purchase::order', 'goods::receipt',
                'purchase::return', 'supplier::invoice', 'supplier', 'supplier::price',
            ],
            'pages' => ['PurchaseRegister'],
        ],
        'inventory' => [
            'resources' => ['product', 'stock', 'stock::adjustment', 'stock::transfer', 'warehouse', 'unit'],
            'pages' => ['StockValuation', 'StockLedger'],
        ],
        'manufacturing' => [
            'resources' => ['bill::of::materials', 'manufacturing::order'],
            'pages' => ['Mrp'],
        ],
        'accounting' => [
            'resources' => ['account', 'journal', 'payment', 'expense', 'tax::rate', 'opening::balance', 'fixed::asset'],
            'pages' => [
                'Reports', 'GeneralLedger', 'ReceivablesAging', 'PayablesAging',
                'PaymentVoucherRegister', 'ReceiptVoucherRegister',
            ],
        ],
        'hr' => [
            'resources' => [
                'employee', 'department', 'designation', 'shift', 'attendance',
                'roster', 'leave::type', 'leave::request', 'payroll::run', 'holiday',
            ],
            'pages' => [],
        ],
    ];

    public function run(): void
    {
        // Shield permissions are command-generated (not migrations). Regenerate so
        // every currently-registered resource/page (incl. Warehouses, Units, the
        // Voucher Registers) is grantable. Idempotent — only adds what's missing;
        // super_admin bypasses the gate so it needs no re-grant.
        Artisan::call('shield:generate', [
            '--all' => true, '--option' => 'permissions', '--panel' => 'admin', '--no-interaction' => true,
        ]);

        $company = Company::query()->where('code', 'DEMO')->first();
        if ($company === null) {
            $this->command->error('Demo company (code DEMO) not found. Run the main DatabaseSeeder first.');

            return;
        }

        /** @var list<string> $allNames */
        $allNames = Permission::query()->pluck('name')->all();

        $rows = [];

        foreach (self::MODULES as $roleName => $spec) {
            // Every permission whose name ends with "_<suffix>" belongs to that
            // resource (view_any_, view_, create_, update_, delete_, delete_any_, …).
            $grant = [];
            foreach ($spec['resources'] as $suffix) {
                foreach ($allNames as $name) {
                    if (str_ends_with($name, '_'.$suffix)) {
                        $grant[] = $name;
                    }
                }
            }

            // Report/utility page permissions for the module (guard: only if present).
            foreach ($spec['pages'] as $page) {
                $pageName = 'page_'.$page;
                if (in_array($pageName, $allNames, true)) {
                    $grant[] = $pageName;
                }
            }

            $grant = array_values(array_unique($grant));

            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($grant);

            // One demo login per role.
            $email = $roleName.'@erp.test';
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => ucfirst($roleName).' User',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
            $user->companies()->syncWithoutDetaching([$company->getKey() => ['is_default' => true]]);
            $user->syncRoles([$roleName]);

            $rows[] = [$roleName, $email, 'password', (string) count($grant)];
        }

        $this->command->info('Demo departmental roles + logins ready (company: '.$company->code.'):');
        $this->command->table(['Role', 'Login email', 'Password', 'Permissions'], $rows);
        $this->command->warn('Admin / super_admin left unchanged.');
    }
}

<?php
// SECURITY: Delete this file immediately after use.
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;">';
echo "=== Migration Runner (individual paths) ===\n\n";

// All migrations added after June 11 (when InfinityFree DB was last migrated).
// Skipping the unique-contact-name constraint — needs duplicate check first.
$migrations = [
    'database/migrations/2026_06_12_000001_add_performance_composite_indexes.php',
    'database/migrations/2026_06_15_000001_add_is_permanently_closed_to_contacts.php',
    'database/migrations/2026_06_15_000002_create_contact_edit_grants_table.php',
    'database/migrations/2026_06_15_000003_add_audit_trail_to_core_tables.php',
    'database/migrations/2026_06_17_000001_add_missing_indexes_and_constraints.php',
    'database/migrations/2026_06_17_000001_add_login_lockout_fields_to_users_table.php',
    'database/migrations/2026_06_17_000002_add_lockout_escalation_fields_to_users_table.php',
    'database/migrations/2026_06_17_000003_add_is_pending_to_advertising_products_table.php',
    'database/migrations/2026_06_18_000001_add_illumination_facing_to_advertising_products_table.php',
    'database/migrations/2026_06_19_000001_drop_webhook_tables.php',
    'database/migrations/2026_06_19_000002_add_last_contacted_at_to_contacts_table.php',
];

foreach ($migrations as $path) {
    $name = basename($path, '.php');
    echo ">>> migrate --path={$path}\n";
    try {
        $exitCode = $kernel->call('migrate', [
            '--path'  => $path,
            '--force' => true,
        ]);
        $output = trim($kernel->output());
        echo ($output ?: '(no output)') . "\n";
        echo "Exit code: {$exitCode}\n\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n\n";
    }
}

echo "NOTE: 2026_06_17_000002_add_unique_constraint_to_contacts_name was SKIPPED.\n";
echo "Run this manually after checking for duplicate contact names:\n";
echo "  SELECT name, COUNT(*) c FROM contacts GROUP BY name HAVING c > 1;\n\n";
echo "=== Done. DELETE THIS FILE NOW. ===\n";
echo '</pre>';

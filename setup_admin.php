<?php
// SECURITY: Delete this file immediately after use.
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$commands = [
    ['migrate', ['--force' => true]],
    ['db:seed', ['--class' => 'RolesAndPermissionsSeeder', '--force' => true]],
    ['config:cache', []],
    ['route:cache', []],
    ['view:cache', []],
];

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;">';
echo "=== Artisan Runner ===\n\n";

foreach ($commands as [$cmd, $args]) {
    echo ">>> php artisan {$cmd}\n";
    $exitCode = $kernel->call($cmd, $args);
    echo $kernel->output();
    echo "Exit code: {$exitCode}\n\n";
}

echo "=== Done. DELETE THIS FILE NOW. ===\n";
echo '</pre>';

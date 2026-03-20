<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$migrations = glob(database_path().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR.'*.php');
$dbMigrations = \Illuminate\Support\Facades\DB::table('migrations')->get()->pluck('migration')->toArray();

echo "FS Count: " . count($migrations) . "\n";
echo "DB Count: " . count($dbMigrations) . "\n";
if (count($migrations) == count($dbMigrations)) {
    echo "RESULT: ALREADY UPDATED (404)\n";
} else {
    echo "RESULT: NEEDS UPDATE\n";
}

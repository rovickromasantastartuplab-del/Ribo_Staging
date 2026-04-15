<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$search = 'RON';
echo "=== SEARCHING FOR '{$search}' IN ALL TABLES ===\n";

$tables = DB::select('SHOW TABLES');
$databaseName = config('database.connections.mysql.database');
$tableKey = "Tables_in_{$databaseName}";

foreach ($tables as $table) {
    $tableName = $table->$tableKey;
    $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
    
    $query = DB::table($tableName);
    $query->where(function($q) use ($columns, $search) {
        foreach ($columns as $column) {
                try {
                $q->orWhere($column, 'like', '%' . $search . '%');
            } catch (\Exception $e) {
                // Skip columns that can't be searched with LIKE
            }
        }
    });
    
    $count = $query->count();
    if ($count > 0) {
        echo "Table: {$tableName} | Found: {$count}\n";
        $results = $query->limit(5)->get();
        foreach ($results as $row) {
            echo "  " . json_encode($row) . "\n";
        }
    }
}

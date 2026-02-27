<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ALL USERS ===\n";
$users = \App\Models\User::select('id', 'name', 'type', 'email', 'created_by')->get();
foreach ($users as $u) {
    echo "ID:{$u->id} | {$u->name} | type:{$u->type} | {$u->email} | created_by:{$u->created_by}\n";
}

echo "\n=== STAFF USERS ===\n";
$staffUsers = \App\Models\User::where('type', 'staff')->get();
foreach ($staffUsers as $u) {
    echo "ID:{$u->id} | {$u->name} | type:{$u->type} | created_by:{$u->created_by}\n";
    
    $createdBy = $u->created_by;
    echo "  createdBy() would return: {$createdBy}\n";
    
    $dropdownUsers = \App\Models\User::where('created_by', $createdBy)
        ->where('type', '!=', 'company')
        ->select('id', 'name', 'email')
        ->get();
    echo "  Dropdown users count: " . $dropdownUsers->count() . "\n";
    foreach ($dropdownUsers as $du) {
        echo "    - ID:{$du->id} {$du->name} ({$du->email})\n";
    }
}

<?php

use App\Models\Role;
use Spatie\Permission\Models\Permission;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Updating Conversations Permissions...\n";

// 1. Create permissions if they don't exist (just in case they haven't run seeder)
$viewPermission = Permission::firstOrCreate(['name' => 'view-conversations', 'guard_name' => 'web'], [
    'module' => 'conversations',
    'label' => 'View Conversations',
    'description' => 'Can view the Conversations Hub and read threads'
]);

$managePermission = Permission::where(['name' => 'manage-conversations', 'guard_name' => 'web'])->first();
if ($managePermission) {
    $managePermission->update([
        'module' => 'conversations',
        'label' => 'Manage Conversations',
        'description' => 'Can access the Conversations Hub, reply to messages, and link threads to CRM records'
    ]);
} else {
    $managePermission = Permission::create(['name' => 'manage-conversations', 'guard_name' => 'web', 'module' => 'conversations', 'label' => 'Manage Conversations', 'description' => '...']);
}

// 2. Find all roles and grant permissions
$roles = Role::all();
foreach ($roles as $role) {
    // If it's a superadmin or a company-related role, they should have these by default for now
    // Especially if they already had the old 'manage-conversations'
    $role->givePermissionTo($viewPermission);
    $role->givePermissionTo($managePermission);
    echo "Updated role: {$role->name}\n";
}

echo "Done!\n";

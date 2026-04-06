<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$angelo = \App\Models\User::where('name', 'like', '%Angelo%')->first();
if ($angelo) {
    echo "ID: {$angelo->id} | Name: {$angelo->name} | Type: {$angelo->type} | CreatedBy: {$angelo->created_by}\n";
    echo "Permissions: " . $angelo->getAllPermissions()->pluck('name')->implode(', ') . "\n";
    
    // Check createdBy()
    Auth::login($angelo);
    echo "createdBy() for Angelo: " . createdBy() . "\n";
} else {
    echo "Angelo NOT found.\n";
}

$contacts = \App\Models\Contact::all();
echo "Found " . $contacts->count() . " contacts total.\n";
foreach ($contacts as $c) {
    echo "ID:{$c->id} | Name:{$c->name} | CreatedBy:{$c->created_by}\n";
}

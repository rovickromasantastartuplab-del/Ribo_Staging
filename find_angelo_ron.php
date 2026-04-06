<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== FINDING ANGELO ===\n";
$angelo = \App\Models\User::where('name', 'like', '%Angelo%')->first();
if ($angelo) {
    echo "Angelo found: ID:{$angelo->id} | name:{$angelo->name} | type:{$angelo->type} | created_by:{$angelo->created_by}\n";
    
    // Check Angelo's permissions if Spatie Permission is used
    if (method_exists($angelo, 'getAllPermissions')) {
        echo "Permissions: " . $angelo->getAllPermissions()->pluck('name')->implode(', ') . "\n";
    }
} else {
    echo "Angelo NOT found.\n";
}

echo "\n=== FINDING CONTACTS WITH RON ===\n";
$contacts = \App\Models\Contact::where('name', 'like', '%RON%')
    ->orWhere('email', 'like', '%RON%')
    ->get();
echo "Found " . $contacts->count() . " contacts with RON's data.\n";
foreach ($contacts as $c) {
    echo "ID:{$c->id} | name:{$c->name} | email:{$c->email} | created_by:{$c->created_by} | assigned_to:{$c->assigned_to}\n";
}

if ($angelo && $contacts->count() > 0) {
    $firstContact = $contacts->first();
    echo "\n=== VISIBILITY CHECK FOR ANGELO ON CONTACT ID {$firstContact->id} ===\n";
    
    // Simulating current user as Angelo
    Auth::login($angelo);
    
    $query = \App\Models\Contact::query()
        ->where('id', $firstContact->id)
        ->where(function ($q) {
            if (auth()->user()->type === 'company' || auth()->user()->can('manage-contacts') || auth()->user()->can('view-contacts')) {
                $q->where('created_by', createdBy());
            } else {
                $q->where('assigned_to', auth()->id());
            }
        });
    
    $visible = $query->exists();
    echo "Is contact visible to Angelo? " . ($visible ? "YES" : "NO") . "\n";
    echo "createdBy() returns: " . createdBy() . "\n";
}

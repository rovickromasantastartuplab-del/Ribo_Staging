<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ANGELO DETAILS ===\n";
$angelo = \App\Models\User::where('name', 'like', '%angelo%')->first();
if ($angelo) {
    echo "ID: {$angelo->id}\n";
    echo "Name: {$angelo->name}\n";
    echo "Type: {$angelo->type}\n";
    echo "Created By: {$angelo->created_by}\n";
    echo "Permissions: " . implode(', ', $angelo->getAllPermissions()->pluck('name')->toArray()) . "\n";
    
    $company = \App\Models\User::find($angelo->created_by);
    if ($company) {
        echo "\n=== COMPANY DETAILS ===\n";
        echo "ID: {$company->id}\n";
        echo "Name: {$company->name}\n";
        echo "Type: {$company->type}\n";
        
        $contactsCount = \App\Models\Contact::where('created_by', $company->id)->count();
        echo "Contacts created by this company: {$contactsCount}\n";
        
        if ($contactsCount > 0) {
            $contacts = \App\Models\Contact::where('created_by', $company->id)->limit(5)->get();
            foreach ($contacts as $c) {
                echo "- Contact ID:{$c->id}, Name:{$c->name}, Assigned To:{$c->assigned_to}\n";
            }
        }
    }
} else {
    echo "Angelo not found.\n";
}

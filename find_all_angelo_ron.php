<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== FINDING ALL ANGELOS ===\n";
$users = \App\Models\User::where('name', 'like', '%angelo%')->get();
foreach ($users as $u) {
    echo "ID:{$u->id} | Name:{$u->name} | Type:{$u->type} | CreatedBy:{$u->created_by}\n";
}

echo "\n=== FINDING ALL COMPANIES ===\n";
$companies = \App\Models\User::where('type', 'company')->get();
foreach ($companies as $c) {
    echo "ID:{$c->id} | Name:{$c->name}\n";
}

echo "\n=== FINDING RON CONTACTS ===\n";
$contacts = \App\Models\Contact::where('name', 'like', '%RON%')
    ->orWhere('email', 'like', '%RON%')
    ->get();
if ($contacts->count() > 0) {
    foreach ($contacts as $c) {
        echo "ID:{$c->id} | Name:{$c->name} | CreatedBy:{$c->created_by}\n";
    }
} else {
    echo "No RON contacts found by name/email.\n";
    echo "Listing first 10 contacts:\n";
    $firstContacts = \App\Models\Contact::limit(10)->get();
    foreach ($firstContacts as $c) {
        echo "ID:{$c->id} | Name:{$c->name} | CreatedBy:{$c->created_by}\n";
    }
}

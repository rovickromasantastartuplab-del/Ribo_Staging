<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ALL CONTACTS ===\n";
$contacts = \App\Models\Contact::all();
echo "Total Contacts: " . $contacts->count() . "\n";
foreach ($contacts as $c) {
    echo "ID:{$c->id} | Name:{$c->name} | Email:{$c->email} | CreatedBy:{$c->created_by} | AccountID:{$c->account_id}\n";
}

echo "\n=== ALL ACCOUNTS ===\n";
$accounts = \App\Models\Account::all();
foreach ($accounts as $a) {
    echo "ID:{$a->id} | Name:{$a->name} | CreatedBy:{$a->created_by}\n";
}

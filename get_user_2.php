<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = \App\Models\User::find(2);
if ($u) {
    echo "ID: {$u->id} | Name: {$u->name} | Type: {$u->type} | CreatedBy: {$u->created_by}\n";
} else {
    echo "User 2 not found.\n";
}

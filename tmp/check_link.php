<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$email = 'ubeeeyk@gmail.com';
$lead = \App\Models\Lead::where('email', $email)->first();
$thread = \App\Models\EmailThread::whereJsonContains('participants', $email)->first();

echo "Email: $email\n";
if ($lead) {
    echo "Lead Found: ID " . $lead->id . " | Name: " . $lead->name . " | CreatedBy: " . $lead->created_by . "\n";
} else {
    echo "Lead Not Found\n";
}

if ($thread) {
    echo "Thread Found: ID " . $thread->id . " | CreatedBy: " . $thread->created_by . "\n";
    echo "Linked Leads Count: " . $thread->leads()->count() . "\n";
    foreach ($thread->leads as $l) {
        echo " - Linked Lead ID: " . $l->id . "\n";
    }
} else {
    echo "Thread Not Found\n";
}

<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Lead;
use App\Models\EmailThread;

$lead = Lead::first();
$thread = EmailThread::first();

if ($lead && $thread) {
    $thread->leads()->syncWithoutDetaching([$lead->id => ['matched_via' => 'manual']]);
    echo "Linked Lead {$lead->id} to Thread {$thread->id}\n";
} else {
    echo "No lead or thread found\n";
}

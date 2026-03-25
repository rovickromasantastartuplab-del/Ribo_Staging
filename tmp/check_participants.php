<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = \App\Models\EmailThread::orderBy('id', 'desc')->first();
if ($t) {
    echo "Thread ID: " . $t->id . "\n";
    echo "Participants JSON: " . json_encode($t->participants) . "\n";
} else {
    echo "Thread not found\n";
}

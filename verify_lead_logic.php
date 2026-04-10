<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Lead;
use App\Models\User;
use App\Services\AI\ConversationAiMemoryService;

$company = User::factory()->create(['type' => 'company']);
$lead = Lead::query()->create([
    'name' => 'Test Lead',
    'email' => 'test@lead.com',
    'created_by' => $company->id
]);

$service = app(ConversationAiMemoryService::class);

try {
    echo "Attempting to show memory for Lead...\n";
    $result = $service->show($lead, $company->id);
    echo "Success! Result contains " . count($result['tasks']) . " tasks.\n";
    print_r($result['summary']->toArray());
} catch (\Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

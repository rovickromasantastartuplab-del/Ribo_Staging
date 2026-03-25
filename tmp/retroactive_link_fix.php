<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\EmailThread;
use App\Models\Lead;
use App\Models\Contact;
use App\Services\GmailService;

echo "Starting robust retroactive conversation linking scan...\n";

// Get all threads that have no lead/contact links
$unlinkedThreads = EmailThread::whereDoesntHave('leads')
    ->whereDoesntHave('contacts')
    ->get();

$total = count($unlinkedThreads);
echo "Found $total unlinked threads. Processing...\n";

$linkedCount = 0;
foreach ($unlinkedThreads as $thread) {
    $acc = $thread->gmailAccount;
    if ($acc) {
        $service = new GmailService($acc);
        
        // Manual implementation of the now-improved autoLinkThread logic
        // but triggered explicitly here.
        $participants = $thread->participants ?? [];
        if (empty($participants)) continue;

        // Extract clean emails
        $emails = [];
        foreach ($participants as $p) {
            if (preg_match('/<(.+?)>/', $p, $matches)) {
                $emails[] = strtolower(trim($matches[1]));
            } else {
                $emails[] = strtolower(trim($p));
            }
        }
        $emails = array_unique(array_filter($emails));

        // Find Leads
        $matchingLeads = Lead::where('created_by', $thread->created_by)
            ->whereIn('email', $emails)
            ->pluck('id');
        
        foreach ($matchingLeads as $leadId) {
            $thread->leads()->syncWithoutDetaching([$leadId => ['matched_via' => 'retroactive_fix']]);
            $linkedCount++;
        }

        // Find Contacts
        $matchingContacts = Contact::where('created_by', $thread->created_by)
            ->whereIn('email', $emails)
            ->pluck('id');
            
        foreach ($matchingContacts as $contactId) {
            $thread->contacts()->syncWithoutDetaching([$contactId => ['matched_via' => 'retroactive_fix']]);
            $linkedCount++;
        }
    }
}

echo "Scan complete. Successfully linked $linkedCount threads.\n";

<?php

namespace App\Services\AI;

use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\Opportunity;
use App\Services\LeadActivityStreamService;
use App\Services\OpportunityActivityStreamService;
use Illuminate\Support\Collection;

class ConversationAiReportContextBuilder
{
    private const MAX_ACTIVITY_ITEMS_PER_STREAM = 120;

    public function __construct(
        private readonly LeadActivityStreamService $leadActivityStreamService,
        private readonly OpportunityActivityStreamService $opportunityActivityStreamService
    ) {
    }

    public function build(
        int $companyId,
        EmailThread $thread,
        string $scope,
        ?Contact $contact = null,
        ?int $opportunityId = null
    ): array {
        $linkedContacts = $this->resolveContacts($thread, $companyId, $contact);
        $opportunities = $this->resolveOpportunities($companyId, $linkedContacts, $scope, $opportunityId);
        $opportunityDetails = $opportunities->take(20);
        $leads = $thread->leads()->where('created_by', $companyId)->get();

        $arr = (float) $opportunities->sum(fn (Opportunity $opp): float => (float) ($opp->amount ?? 0));
        $mrr = $arr / 12;

        [$leadActivity, $leadActivityMeta] = $this->collectLeadActivityStream($leads);
        [$opportunityActivity, $opportunityActivityMeta] = $this->collectOpportunityActivityStream($opportunities);

        return [
            'scope' => $scope,
            'threads' => [
                [
                    'id' => $thread->id,
                    'subject' => (string) ($thread->subject ?? ''),
                    'snippet' => (string) ($thread->snippet ?? ''),
                    'participants' => $thread->participants ?? [],
                    'last_message_at' => optional($thread->last_message_at)->toIso8601String(),
                ],
            ],
            'crm' => [
                'account' => [
                    'id' => $linkedContacts->first()?->account_id,
                    'name' => $linkedContacts->first()?->account?->name ?? 'Unassigned Account',
                ],
                'financials' => [
                    // ARR/MRR are computed from scoped CRM deal amounts for deterministic reporting.
                    'arr' => round($arr, 2),
                    'mrr' => round($mrr, 2),
                    'active_deals_count' => $opportunities->count(),
                ],
                'lead' => $leads->first() ? [
                    'id' => $leads->first()->id,
                    'name' => $leads->first()->name,
                    'value' => (float) ($leads->first()->value ?? 0),
                    'status' => (string) ($leads->first()->status ?? ''),
                ] : null,
                'opportunities' => $opportunityDetails->map(function (Opportunity $opportunity): array {
                    return [
                        'id' => $opportunity->id,
                        'name' => $opportunity->name,
                        'amount' => (float) ($opportunity->amount ?? 0),
                        'status' => (string) ($opportunity->status ?? 'active'),
                        'stage' => (string) ($opportunity->opportunityStage?->name ?? 'Unknown'),
                        'close_date' => optional($opportunity->close_date)->toDateString(),
                        'contact' => $opportunity->contact?->name,
                    ];
                })->values()->all(),
                'relationships' => $linkedContacts->map(fn (Contact $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'role' => $item->position ?: 'Stakeholder',
                    'account' => $item->account?->name,
                ])->values()->all(),
            ],
            'activity_streams' => [
                'lead' => $leadActivity,
                'opportunity' => $opportunityActivity,
                'meta' => [
                    'lead_scanned_count' => $leadActivityMeta['scanned_count'],
                    'lead_included_count' => $leadActivityMeta['included_count'],
                    'opportunity_scanned_count' => $opportunityActivityMeta['scanned_count'],
                    'opportunity_included_count' => $opportunityActivityMeta['included_count'],
                ],
            ],
        ];
    }

    public function scopeOptions(int $companyId, EmailThread $thread): array
    {
        $contacts = $this->resolveContacts($thread, $companyId, null);
        $opportunities = $this->resolveOpportunities($companyId, $contacts, 'all-opps', null);

        return [
            'opportunities' => $opportunities->map(fn (Opportunity $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => (float) ($item->amount ?? 0),
                'stage' => (string) ($item->opportunityStage?->name ?? 'Unknown'),
            ])->values()->all(),
        ];
    }

    private function resolveContacts(EmailThread $thread, int $companyId, ?Contact $contact): Collection
    {
        if ($contact !== null) {
            return collect([$contact->loadMissing('account')]);
        }

        return $thread->contacts()
            ->where('contacts.created_by', $companyId)
            ->with('account')
            ->get();
    }

    private function resolveOpportunities(
        int $companyId,
        Collection $contacts,
        string $scope,
        ?int $opportunityId
    ): Collection {
        if ($scope === 'leads-only') {
            return collect();
        }

        $query = Opportunity::query()
            ->where('created_by', $companyId)
            ->with(['opportunityStage', 'contact']);

        $contactIds = $contacts->pluck('id')->filter()->values();
        $accountIds = $contacts->pluck('account_id')->filter()->values();

        if ($contactIds->isEmpty() && $accountIds->isEmpty()) {
            return collect();
        }

        $query->where(function ($inner) use ($contactIds, $accountIds): void {
            $applied = false;
            if ($contactIds->isNotEmpty()) {
                $inner->whereIn('contact_id', $contactIds->all());
                $applied = true;
            }
            if ($accountIds->isNotEmpty()) {
                if ($applied) {
                    $inner->orWhereIn('account_id', $accountIds->all());
                } else {
                    $inner->whereIn('account_id', $accountIds->all());
                }
            }
        });

        if ($scope === 'specific-opportunity') {
            if ($opportunityId === null) {
                return collect();
            }

            $query->where('id', $opportunityId);
        }

        return $query
            ->orderByDesc('updated_at')
            ->get();
    }

    private function collectLeadActivityStream(Collection $leads): array
    {
        $fullStream = $leads
            ->flatMap(function ($lead) {
                return $this->leadActivityStreamService
                    ->streamItemsCollection($lead)
                    ->map(fn ($item) => $this->leadActivityStreamService->serializeItem($item));
            })
            ->sortByDesc('created_at')
            ->values();

        $included = $fullStream
            ->take(self::MAX_ACTIVITY_ITEMS_PER_STREAM)
            ->values()
            ->all();

        return [
            $included,
            [
                'scanned_count' => $fullStream->count(),
                'included_count' => count($included),
            ],
        ];
    }

    private function collectOpportunityActivityStream(Collection $opportunities): array
    {
        $fullStream = $opportunities
            ->flatMap(function (Opportunity $opportunity) {
                return $this->opportunityActivityStreamService
                    ->streamItemsCollection($opportunity)
                    ->map(fn ($item) => $this->opportunityActivityStreamService->serializeItem($item));
            })
            ->sortByDesc('created_at')
            ->values();

        $included = $fullStream
            ->take(self::MAX_ACTIVITY_ITEMS_PER_STREAM)
            ->values()
            ->all();

        return [
            $included,
            [
                'scanned_count' => $fullStream->count(),
                'included_count' => count($included),
            ],
        ];
    }
}

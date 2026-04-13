<?php

namespace App\Services\AI;

use App\Models\Account;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Models\Opportunity;
use App\Services\AI\Reports\ActivityStreamDigestBuilder;
use App\Services\LeadActivityStreamService;
use App\Services\OpportunityActivityStreamService;
use Illuminate\Support\Collection;

class ConversationAiReportContextBuilder
{
    private const MAX_ACTIVITY_ITEMS_PER_STREAM = 120;

    public function __construct(
        private readonly LeadActivityStreamService $leadActivityStreamService,
        private readonly OpportunityActivityStreamService $opportunityActivityStreamService,
        private readonly ActivityStreamDigestBuilder $activityStreamDigestBuilder
    ) {
    }

    public function build(
        int $companyId,
        EmailThread $thread,
        string $scope,
        ?Contact $contact = null,
        ?int $opportunityId = null
    ): array {
        $leads = $thread->leads()->where('created_by', $companyId)->get();
        $linkedContacts = $this->resolveContacts($thread, $companyId, $contact, $leads);
        $opportunities = $this->resolveOpportunities($companyId, $linkedContacts, $scope, $opportunityId);
        $opportunityDetails = $opportunities->take(20);

        $arr = (float) $opportunities->sum(fn (Opportunity $opp): float => (float) ($opp->amount ?? 0));
        $mrr = $arr / 12;

        [$leadActivity, $leadActivityMeta, $leadFullStream] = $this->collectLeadActivityStream($leads);
        [$opportunityActivity, $opportunityActivityMeta, $opportunityFullStream] = $this->collectOpportunityActivityStream($opportunities);

        $combinedFullStream = collect($leadFullStream)
            ->merge($opportunityFullStream)
            ->sortByDesc('created_at')
            ->values()
            ->all();

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
                'account' => $this->resolveAccountSnapshot($linkedContacts),
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
                'relationships' => $linkedContacts->map(function ($item): array {
                    $account = $item->account ?? null;
                    if ($account === null && isset($item->account_id) && $item->account_id) {
                        $account = Account::query()->find($item->account_id);
                    }

                    return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'email' => $item->email,
                    'role' => $item->position ?: 'Stakeholder',
                    'account' => $account?->name,
                    ];
                })->values()->all(),
            ],
            'activity_streams' => [
                'lead' => $leadActivity,
                'opportunity' => $opportunityActivity,
                'historical_summary' => [
                    'lead' => $this->activityStreamDigestBuilder->build($leadFullStream),
                    'opportunity' => $this->activityStreamDigestBuilder->build($opportunityFullStream),
                    'combined' => $this->activityStreamDigestBuilder->build($combinedFullStream),
                ],
                'meta' => [
                    'lead_scanned_count' => $leadActivityMeta['scanned_count'],
                    'lead_included_count' => $leadActivityMeta['included_count'],
                    'lead_older_count' => max(0, $leadActivityMeta['scanned_count'] - $leadActivityMeta['included_count']),
                    'opportunity_scanned_count' => $opportunityActivityMeta['scanned_count'],
                    'opportunity_included_count' => $opportunityActivityMeta['included_count'],
                    'opportunity_older_count' => max(0, $opportunityActivityMeta['scanned_count'] - $opportunityActivityMeta['included_count']),
                    'combined_scanned_count' => count($combinedFullStream),
                ],
            ],
        ];
    }

    public function scopeOptions(int $companyId, EmailThread $thread): array
    {
        $leads = $thread->leads()->where('created_by', $companyId)->get();
        $contacts = $this->resolveContacts($thread, $companyId, null, $leads);
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

    private function resolveContacts(EmailThread $thread, int $companyId, ?Contact $contact, Collection $leads): Collection
    {
        if ($contact !== null) {
            return collect([$contact->loadMissing('account')]);
        }

        $threadContacts = $thread->contacts()
            ->where('contacts.created_by', $companyId)
            ->with('account')
            ->get();

        $fallbackContacts = $this->resolveLeadRelatedContactsFallback($companyId, $leads);

        if ($threadContacts->isEmpty()) {
            return $fallbackContacts;
        }

        if ($fallbackContacts->isEmpty()) {
            return $threadContacts;
        }

        return $this->dedupeContacts($threadContacts->merge($fallbackContacts));
    }

    private function resolveLeadRelatedContactsFallback(int $companyId, Collection $leads): Collection
    {
        $leadCandidates = $leads
            ->map(function ($lead): array {
                return [
                    'company' => trim((string) ($lead->company ?? '')),
                    'email' => trim((string) ($lead->email ?? '')),
                    'is_converted' => (bool) ($lead->is_converted ?? false),
                ];
            })
            ->filter(fn (array $item): bool => $item['company'] !== '' || $item['email'] !== '')
            ->values();

        if ($leadCandidates->isEmpty()) {
            return collect();
        }

        // Prefer converted-lead anchors first to match Lead page "Related Accounts" behavior.
        $preferredCandidates = $leadCandidates->where('is_converted', true)->values();
        if ($preferredCandidates->isEmpty()) {
            $preferredCandidates = $leadCandidates;
        }

        $accounts = Account::query()
            ->where('created_by', $companyId)
            ->where(function ($query) use ($preferredCandidates): void {
                foreach ($preferredCandidates as $candidate) {
                    $query->orWhere(function ($nested) use ($candidate): void {
                        if ($candidate['company'] !== '') {
                            $nested->where('name', 'like', '%' . $candidate['company'] . '%');
                        }
                        if ($candidate['email'] !== '') {
                            if ($candidate['company'] !== '') {
                                $nested->orWhere('email', $candidate['email']);
                            } else {
                                $nested->where('email', $candidate['email']);
                            }
                        }
                    });
                }
            })
            ->get(['id', 'name', 'email', 'created_by']);

        if ($accounts->isEmpty()) {
            return collect();
        }

        $contacts = Contact::query()
            ->where('created_by', $companyId)
            ->whereIn('account_id', $accounts->pluck('id')->all())
            ->with('account')
            ->get();

        if ($contacts->isNotEmpty()) {
            return $contacts;
        }

        // If related accounts exist but no contacts are attached, create synthetic stakeholder rows
        // so report context still reflects account-level relationship coverage.
        return $accounts->map(function (Account $account) {
            return (object) [
                'id' => null,
                'name' => $account->name,
                'email' => $account->email,
                'position' => 'Account',
                'account_id' => $account->id,
                'account' => $account,
            ];
        })->values();
    }

    private function resolveAccountSnapshot(Collection $linkedContacts): array
    {
        $first = $linkedContacts->first();
        if ($first === null) {
            return [
                'id' => null,
                'name' => 'Unassigned Account',
            ];
        }

        $accountId = $first->account_id ?? null;
        $accountName = $first->account?->name ?? null;

        if ($accountName === null && $accountId) {
            $accountName = Account::query()->where('id', $accountId)->value('name');
        }

        return [
            'id' => $accountId,
            'name' => $accountName ?? 'Unassigned Account',
        ];
    }

    private function dedupeContacts(Collection $contacts): Collection
    {
        return $contacts
            ->unique(function ($item): string {
                $id = $item->id ?? null;
                if ($id !== null) {
                    return 'id:' . (string) $id;
                }

                $accountId = $item->account_id ?? null;
                $email = strtolower(trim((string) ($item->email ?? '')));
                $name = strtolower(trim((string) ($item->name ?? '')));

                return 'fallback:' . (string) $accountId . ':' . $email . ':' . $name;
            })
            ->values();
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
            ->values()
            ->all();

        $included = collect($fullStream)
            ->take(self::MAX_ACTIVITY_ITEMS_PER_STREAM)
            ->values()
            ->all();

        return [
            $included,
            [
                'scanned_count' => count($fullStream),
                'included_count' => count($included),
            ],
            $fullStream,
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
            ->values()
            ->all();

        $included = collect($fullStream)
            ->take(self::MAX_ACTIVITY_ITEMS_PER_STREAM)
            ->values()
            ->all();

        return [
            $included,
            [
                'scanned_count' => count($fullStream),
                'included_count' => count($included),
            ],
            $fullStream,
        ];
    }
}

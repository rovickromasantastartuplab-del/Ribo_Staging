<?php

namespace Tests\Unit\AI;

use App\Services\AI\Reports\ActivityStreamDigestBuilder;
use Tests\TestCase;

class ActivityStreamDigestBuilderTest extends TestCase
{
    public function test_it_builds_full_stream_digest_from_serialized_items(): void
    {
        $builder = new ActivityStreamDigestBuilder();

        $digest = $builder->build([
            [
                'activity_type' => 'email',
                'title' => 'Support ticket escalated',
                'description' => 'Critical issue blocked rollout',
                'created_at' => '2026-04-10T10:00:00+00:00',
            ],
            [
                'activity_type' => 'comment',
                'title' => 'Champion confirmed expansion interest',
                'description' => 'Positive momentum from operations',
                'created_at' => '2026-04-12T09:00:00+00:00',
            ],
            [
                'activity_type' => 'message',
                'title' => 'Follow-up call scheduled',
                'description' => 'Next steps aligned',
                'created_at' => '2026-04-13T08:30:00+00:00',
            ],
        ]);

        $this->assertSame(3, $digest['total_count']);
        $this->assertSame(1, $digest['by_activity_type']['email']);
        $this->assertSame(1, $digest['by_activity_type']['comment']);
        $this->assertSame(1, $digest['by_activity_type']['message']);
        $this->assertSame(1, $digest['support_event_count']);
        $this->assertSame(1, $digest['risk_event_count']);
        $this->assertSame(1, $digest['positive_event_count']);
        $this->assertSame('2026-04-10T10:00:00+00:00', $digest['oldest_at']);
        $this->assertSame('2026-04-13T08:30:00+00:00', $digest['latest_at']);
    }
}

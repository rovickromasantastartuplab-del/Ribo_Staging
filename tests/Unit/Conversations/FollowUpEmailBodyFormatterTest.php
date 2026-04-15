<?php

namespace Tests\Unit\Conversations;

use App\Services\Conversations\FollowUpEmailBodyFormatter;
use Tests\TestCase;

class FollowUpEmailBodyFormatterTest extends TestCase
{
    public function test_it_converts_plain_text_follow_up_content_into_html_paragraphs(): void
    {
        $formatter = app(FollowUpEmailBodyFormatter::class);

        $formatted = $formatter->format("Hi {FirstName},\n\nJust following up on my previous email.\nWould love to hear your thoughts.\n\nBest,\n{SenderName}\n\n{TrackingPixel}");

        $this->assertSame(
            '<p>Hi {FirstName},</p><p>Just following up on my previous email.<br />' . "\n" . 'Would love to hear your thoughts.</p><p>Best,<br />' . "\n" . '{SenderName}</p>{TrackingPixel}',
            $formatted
        );
    }

    public function test_it_leaves_existing_html_follow_up_content_unchanged(): void
    {
        $formatter = app(FollowUpEmailBodyFormatter::class);

        $html = '<p>Hi {FirstName},</p><p>Just following up.</p><p>Best,<br/>{SenderName}</p>{TrackingPixel}';

        $this->assertSame($html, $formatter->format($html));
    }
}

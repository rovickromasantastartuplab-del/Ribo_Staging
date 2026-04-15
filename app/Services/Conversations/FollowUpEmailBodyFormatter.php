<?php

namespace App\Services\Conversations;

class FollowUpEmailBodyFormatter
{
    private const TRACKING_PIXEL_TOKEN = '{TrackingPixel}';
    private const TRACKING_PIXEL_SENTINEL = '__FOLLOW_UP_TRACKING_PIXEL__';

    public function format(string $body): string
    {
        $bodyWithSentinel = str_replace(self::TRACKING_PIXEL_TOKEN, self::TRACKING_PIXEL_SENTINEL, trim($body));
        $bodyForDetection = str_replace(self::TRACKING_PIXEL_SENTINEL, '', $bodyWithSentinel);

        if ($bodyWithSentinel === '') {
            return '';
        }

        if ($this->containsHtml($bodyForDetection)) {
            return str_replace(self::TRACKING_PIXEL_SENTINEL, self::TRACKING_PIXEL_TOKEN, $bodyWithSentinel);
        }

        $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/', $bodyWithSentinel) ?: [];

        $formatted = array_map(function (string $paragraph): string {
            $trimmedParagraph = trim($paragraph);

            if ($trimmedParagraph === self::TRACKING_PIXEL_SENTINEL) {
                return self::TRACKING_PIXEL_SENTINEL;
            }

            return '<p>' . nl2br(e($trimmedParagraph), true) . '</p>';
        }, array_filter($paragraphs, static fn (string $paragraph): bool => trim($paragraph) !== ''));

        return str_replace(self::TRACKING_PIXEL_SENTINEL, self::TRACKING_PIXEL_TOKEN, implode('', $formatted));
    }

    private function containsHtml(string $body): bool
    {
        return $body !== strip_tags($body);
    }
}

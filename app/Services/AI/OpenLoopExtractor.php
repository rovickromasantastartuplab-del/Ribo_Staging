<?php

namespace App\Services\AI;

use App\Models\EmailThread;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OpenLoopExtractor
{
    /**
     * Extract open-loop candidates from recent thread messages.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractFromThread(EmailThread $thread): array
    {
        $messages = $this->loadRecentMessages($thread);
        if ($messages->isEmpty()) {
            return [];
        }

        $candidates = [];
        foreach ($messages as $message) {
            $text = $this->normalizeWhitespace(
                (string) ($message->body_preview ?? '') . ' ' . strip_tags((string) ($message->body_html ?? ''))
            );

            foreach ($this->splitIntoSentences($text) as $sentence) {
                $confidence = $this->confidenceForSentence($sentence);
                if ($confidence === null) {
                    continue;
                }

                $title = $this->titleFromSentence($sentence);
                $loopKey = $this->normalizeLoopKey($title);
                if ($loopKey === '') {
                    continue;
                }

                $key = $thread->id . ':' . $loopKey;
                $candidate = [
                    'title' => $title,
                    'loop_key' => $loopKey,
                    'confidence' => $confidence,
                    'thread_id' => $thread->id,
                    'message_id' => $message->id,
                    'detected_at' => optional($message->sent_at)->toIso8601String() ?? now()->toIso8601String(),
                    'evidence_hash' => sha1($loopKey . '|' . $thread->id . '|' . $message->id),
                ];

                if (!isset($candidates[$key]) || ($candidates[$key]['confidence'] === 'weak' && $confidence === 'strong')) {
                    $candidates[$key] = $candidate;
                }
            }
        }

        return array_values($candidates);
    }

    public function normalizeLoopKey(string $value): string
    {
        $key = mb_strtolower($this->normalizeWhitespace($value));
        $key = preg_replace('/^(can you|could you|would you|please|kindly|i will|we will|let us|lets)\s+/i', '', $key) ?? $key;
        $key = preg_replace('/\b(the|a|an)\b/i', ' ', $key) ?? $key;
        $key = preg_replace('/[^a-z0-9\s]/', ' ', $key) ?? $key;
        $key = preg_replace('/\s+/', ' ', trim($key)) ?? trim($key);

        return $key;
    }

    private function loadRecentMessages(EmailThread $thread): Collection
    {
        if ($thread->relationLoaded('messages')) {
            /** @var Collection $messages */
            $messages = $thread->messages;

            return $messages->sortByDesc('sent_at')->take(10)->values();
        }

        return $thread->messages()
            ->orderByDesc('sent_at')
            ->limit(10)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function splitIntoSentences(string $text): array
    {
        $raw = preg_split('/[\r\n]+|(?<=[.!?])\s+/u', $text) ?: [];

        return collect($raw)
            ->map(fn (string $segment) => trim($segment))
            ->filter(fn (string $segment) => Str::length($segment) >= 12)
            ->take(8)
            ->values()
            ->all();
    }

    private function confidenceForSentence(string $sentence): ?string
    {
        $value = mb_strtolower($sentence);

        $hasActionVerb = (bool) preg_match('/\b(send|share|confirm|review|follow up|schedule|book|update|provide|reply|call|meet)\b/i', $value);
        if (!$hasActionVerb) {
            return null;
        }

        $isWeak = (bool) preg_match('/\b(maybe|might|someday|sometime|next month|if possible)\b/i', $value);
        if ($isWeak) {
            return 'weak';
        }

        $isStrong = (bool) preg_match('/\b(please|can you|could you|would you|i will|we will|let\'?s|need to|by [a-z]+|today|tomorrow)\b/i', $value);

        return $isStrong ? 'strong' : 'weak';
    }

    private function titleFromSentence(string $sentence): string
    {
        $title = $this->normalizeWhitespace($sentence);
        $title = preg_replace('/^(can you|could you|would you|please|kindly)\s+/i', '', $title) ?? $title;
        $title = preg_replace('/[.!?]+$/', '', $title) ?? $title;

        return mb_strtolower(trim($title));
    }

    private function normalizeWhitespace(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
    }
}

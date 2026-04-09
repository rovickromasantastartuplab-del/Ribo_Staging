<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiTask;
use App\Models\AiTriageResult;
use App\Models\Contact;
use App\Models\EmailThread;
use App\Services\AI\ConversationAiFeedbackService;
use App\Services\AI\Rules\ConversationAiRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationAiFeedbackController extends Controller
{
    public function __construct(
        private readonly ConversationAiFeedbackService $feedbackService,
        private readonly ConversationAiRules $rules
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threadId' => ['required', 'integer'],
            'triageResultId' => ['nullable', 'integer'],
            'contactId' => ['nullable', 'integer'],
            'taskId' => ['nullable', 'integer'],
            'error_type' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $companyId = (int) auth()->user()->creatorId();
        $config = $this->rules->resolveConfig();
        if (!$this->rules->isAvailable($config)) {
            return response()->json(['message' => 'AI unavailable'], 422);
        }

        $thread = EmailThread::query()
            ->where('id', (int) $validated['threadId'])
            ->where('created_by', $companyId)
            ->firstOrFail();

        $triageResult = null;
        if (!empty($validated['triageResultId'])) {
            $triageResult = AiTriageResult::query()
                ->where('id', (int) $validated['triageResultId'])
                ->where('created_by', $companyId)
                ->where('email_thread_id', $thread->id)
                ->firstOrFail();
        }

        $contact = null;
        if (!empty($validated['contactId'])) {
            $contact = Contact::query()
                ->where('id', (int) $validated['contactId'])
                ->where('created_by', $companyId)
                ->firstOrFail();
        }

        $task = null;
        if (!empty($validated['taskId'])) {
            $task = AiTask::query()
                ->where('id', (int) $validated['taskId'])
                ->where('created_by', $companyId)
                ->firstOrFail();
        }

        $feedback = $this->feedbackService->log(
            $companyId,
            $thread,
            $triageResult,
            $contact,
            $task,
            $validated['error_type'] ?? null,
            $validated['notes'] ?? null,
            $validated['payload'] ?? null
        );

        return response()->json([
            'data' => [
                'id' => $feedback->id,
                'error_type' => $feedback->error_type,
                'logged_at' => optional($feedback->logged_at)->toIso8601String(),
            ],
        ]);
    }
}

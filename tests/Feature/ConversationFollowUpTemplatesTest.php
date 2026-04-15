<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\CheckPlanAccess;
use App\Http\Middleware\EnsureOnboardingCompleted;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ShareGlobalSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class ConversationFollowUpTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_up_templates_endpoint_returns_plain_text_bodies_for_editor_use(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this
            ->withoutMiddleware([
                CheckInstallation::class,
                CheckPlanAccess::class,
                EnsureOnboardingCompleted::class,
                HandleAppearance::class,
                HandleInertiaRequests::class,
                ShareGlobalSettings::class,
                PermissionMiddleware::class,
            ])
            ->actingAs($user)
            ->getJson('/api/conversations/follow-up-templates');

        $response
            ->assertOk()
            ->assertJsonPath('templates.0.body', "Hi {FirstName},\n\nJust a quick nudge on this to make sure it didn't get buried in your inbox. Would love to hear your thoughts when you have a moment.\n\nBest,\n{SenderName}\n\n{TrackingPixel}")
            ->assertJsonMissing(['body' => '<p>Hi {FirstName},</p>']);
    }
}

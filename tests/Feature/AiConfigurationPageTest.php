<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiConfigurationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_render_ai_settings_page(): void
    {
        $user = User::factory()->superAdmin()->create(['user_number' => 'USR-000001']);

        $response = $this->actingAs($user)
            ->get('/admin/settings/ai-configuration');

        if ($response->status() >= 500) {
            $this->fail('AI settings page returned ' . $response->status() . ': ' . substr((string) $response->getContent(), 0, 2000));
        }

        $response->assertOk();
    }
}

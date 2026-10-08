<?php

namespace Tests\Feature;

use App\Livewire\Api\Tokens;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_can_issue_update_and_revoke_owned_token(): void
    {
        $user = User::factory()->create();
        $component = Livewire::actingAs($user)->test(Tokens::class)
            ->set('apiTokenName', 'Reader')->set('apiTokenTrustedIp', '203.0.113.10')
            ->call('createApiToken')->assertHasNoErrors();
        $token = ApiToken::query()->sole();
        $plain = $component->get('newApiTokenPlainText');
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertSame($user->id, $token->user_id);
        $component->set('apiTokenTrustedIps.'.$token->id, '203.0.113.20')->call('updateApiTokenTrustedIp', $token->id);
        $this->assertSame('203.0.113.20', $token->fresh()->trusted_ip);
        $component->call('revokeApiToken', $token->id)->assertSet('newApiTokenPlainText', null);
        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_token_mutations_cannot_affect_other_users(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = ApiToken::query()->create(['user_id' => $owner->id, 'name' => 'Private',
            'token_hash' => hash('sha256', 'private'), 'trusted_ip' => '203.0.113.10']);
        Livewire::actingAs($other)->test(Tokens::class)->assertDontSee('Private')
            ->set('apiTokenTrustedIps.'.$token->id, '203.0.113.20')->call('updateApiTokenTrustedIp', $token->id)
            ->call('revokeApiToken', $token->id);
        $this->assertNull($token->fresh()->revoked_at);
        $this->assertSame('203.0.113.10', $token->fresh()->trusted_ip);
    }

    public function test_unverified_user_cannot_mount_token_management(): void
    {
        Livewire::actingAs(User::factory()->unverified()->create())->test(Tokens::class)->assertForbidden();
    }
}

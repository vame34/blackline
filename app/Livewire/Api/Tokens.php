<?php

namespace App\Livewire\Api;

use App\Models\ApiToken;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

final class Tokens extends Component
{
    use WithPagination;

    public string $apiTokenName = 'Signal viewer';

    public int $apiTokenExpiresDays = 365;

    public string $apiTokenTrustedIp = '';

    public array $apiTokenTrustedIps = [];

    public ?string $newApiTokenPlainText = null;

    public function boot(): void
    {
        abort_unless(auth()->check() && ! auth()->user()->isBanned() && auth()->user()->hasVerifiedEmail(), 403);
    }

    public function mount(): void
    {
        $this->apiTokenTrustedIp = (string) request()->ip();
    }

    public function createApiToken(): void
    {
        $data = $this->validate([
            'apiTokenName' => ['required', 'string', 'max:120'],
            'apiTokenExpiresDays' => ['required', 'integer', 'min:1', 'max:3650'],
            'apiTokenTrustedIp' => ['required', 'ip'],
        ]);
        $issued = ApiToken::issueToken();
        ApiToken::query()->create([
            'user_id' => auth()->id(), 'name' => trim($data['apiTokenName']), 'token_hash' => $issued['hash'],
            'expires_at' => now()->addDays($data['apiTokenExpiresDays']), 'trusted_ip' => trim($data['apiTokenTrustedIp']),
        ]);
        $this->newApiTokenPlainText = $issued['plain_text'];
        $this->resetPage();
    }

    public function revokeApiToken(int $tokenId): void
    {
        $this->ownedTokens()->whereKey($tokenId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->newApiTokenPlainText = null;
    }

    public function updateApiTokenTrustedIp(int $tokenId): void
    {
        $newIp = trim((string) ($this->apiTokenTrustedIps[$tokenId] ?? ''));
        validator(['trusted_ip' => $newIp], ['trusted_ip' => ['required', 'ip']])->validate();
        $this->ownedTokens()->whereKey($tokenId)->whereNull('revoked_at')->update(['trusted_ip' => $newIp]);
    }

    public function render()
    {
        $apiTokens = $this->ownedTokens()->latest()->paginate(20);
        foreach ($apiTokens as $token) {
            $this->apiTokenTrustedIps[$token->id] ??= (string) ($token->trusted_ip ?? '');
        }

        return view('livewire.api.tokens', compact('apiTokens'));
    }

    private function ownedTokens(): Builder
    {
        return ApiToken::query()->where('user_id', auth()->id());
    }
}

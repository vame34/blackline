<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Console\Command;

class CreateApiTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:token:create
        {email : User email}
        {--name=Default API key : Token label}
        {--expires-days= : Expiration period in days, use 0 for no expiry}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an API token for realtime signal API access.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = trim((string) $this->option('name'));
        $expiresDaysOption = $this->option('expires-days');

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->error('User not found: '.$email);

            return self::FAILURE;
        }

        $issued = ApiToken::issueToken();

        $expiresAt = ApiToken::defaultExpiresAt();
        if ($expiresDaysOption !== null) {
            $days = (int) $expiresDaysOption;
            $expiresAt = $days > 0 ? now()->addDays($days) : null;
        }

        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $name !== '' ? $name : 'Default API key',
            'token_hash' => $issued['hash'],
            'expires_at' => $expiresAt,
        ]);

        $this->newLine();
        $this->info('API token created. Store it securely; it will never be shown again.');
        $this->line('User: '.$user->email.' (#'.$user->id.')');
        $this->line('Token ID: '.$token->id);
        $this->line('Expires at: '.($expiresAt?->toDateTimeString() ?? 'never'));
        $this->newLine();
        $this->line($issued['plain_text']);
        $this->newLine();

        return self::SUCCESS;
    }
}

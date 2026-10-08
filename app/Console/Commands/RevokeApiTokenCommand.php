<?php

namespace App\Console\Commands;

use App\Models\ApiToken;
use Illuminate\Console\Command;

class RevokeApiTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:token:revoke {token_id : API token id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke an API token immediately.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = (int) $this->argument('token_id');

        $token = ApiToken::query()->find($id);
        if (! $token) {
            $this->error('Token not found: '.$id);

            return self::FAILURE;
        }

        if ($token->revoked_at !== null) {
            $this->warn('Token is already revoked.');

            return self::SUCCESS;
        }

        $token->forceFill([
            'revoked_at' => now(),
        ])->save();

        $this->info('Token revoked: #'.$token->id);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\BootstrapAdminService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Explicit operator-only privilege bootstrap; never receives passwords or authentication tokens. */
class BootstrapAdmin extends Command
{
    protected $signature = 'admin:bootstrap {email : Email of an existing active account} {--force : Confirm the privilege grant without prompting}';

    protected $description = 'Grant initial dashboard administration permissions when no dashboard administrator exists';

    /** Validate the target, require explicit confirmation and report controlled failures without account secrets. */
    public function handle(BootstrapAdminService $bootstrap): int
    {
        $email = Str::lower(trim($this->argument('email')));
        try {
            Validator::make(['email' => $email], ['email' => ['required', 'string', 'email:rfc', 'max:254']])->validate();
            if (! $this->option('force') && (! $this->input->isInteractive() || ! $this->confirm('Grant all dashboard permissions to this existing account?'))) {
                $this->warn('Cancelled. No permissions were granted.');

                return self::FAILURE;
            }
            $bootstrap->bootstrap($email);
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->first());

            return self::FAILURE;
        }
        $this->info('Initial administrator configured. No credentials were changed.');

        return self::SUCCESS;
    }
}

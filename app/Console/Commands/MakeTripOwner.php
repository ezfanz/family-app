<?php

namespace App\Console\Commands;

use App\Enums\TripRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trip:owner {email : Email of a registered user} {--trip= : Trip id (defaults to the only trip)}')]
#[Description('Make a registered user an owner of a trip')]
class MakeTripOwner extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();

        if ($user === null) {
            $this->error('No user with that email. Register in the app first.');

            return self::FAILURE;
        }

        $trip = $this->option('trip') !== null
            ? Trip::find($this->option('trip'))
            : (Trip::count() === 1 ? Trip::first() : null);

        if ($trip === null) {
            $this->error('Trip not found. Pass --trip=<id> when there is more than one trip.');

            return self::FAILURE;
        }

        if (! $trip->addMember($user, TripRole::Owner)) {
            $trip->changeRole($user, TripRole::Owner);
        }

        $this->info("{$user->email} is now an owner of \"{$trip->name}\".");

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Enums\TripRole;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Signs up the trip's family and friends and adds them to the Japan trip as
 * editors, so they can sign in without an invite. Who and the first
 * password come from TRIP_MEMBERS and TRIP_MEMBER_PASSWORD in .env, never
 * from code. Safe to run again: existing people keep their password and role.
 *
 * Run: php artisan db:seed --class=TripMembersSeeder --force
 */
class TripMembersSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('itinerary.seed_member_password');
        $members = $this->members((string) config('itinerary.seed_members'));
        if (blank($password) || $members === []) {
            $this->command?->warn('Set TRIP_MEMBERS and TRIP_MEMBER_PASSWORD in .env first. Nothing was added.');

            return;
        }

        $trip = Trip::where('name', TripSeeder::TRIP_NAME)->first() ?? Trip::oldest()->first();
        if ($trip === null) {
            $this->command?->warn('No trip yet. Run the TripSeeder first.');

            return;
        }

        foreach ($members as ['name' => $name, 'email' => $email]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);
            $added = $trip->addMember($user, TripRole::Editor);

            $this->command?->line(sprintf(
                '%s <%s>: %s, %s',
                $user->name,
                $email,
                $user->wasRecentlyCreated ? 'account created' : 'account already there',
                $added ? 'added to the trip as editor' : 'already on the trip',
            ));
        }
    }

    /**
     * "Amalina:amalina@example.com, Haikal:haikal@example.com" → name and lower-case email.
     *
     * @return list<array{name: string, email: string}>
     */
    private function members(string $list): array
    {
        return collect(explode(',', $list))
            ->map(fn (string $pair): array => array_map('trim', explode(':', $pair, 2)))
            ->filter(fn (array $parts): bool => count($parts) === 2 && filter_var($parts[1], FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn (array $parts): array => [
                'name' => $parts[0] !== '' ? Str::limit($parts[0], 60, '') : Str::before($parts[1], '@'),
                'email' => Str::lower($parts[1]),
            ])
            ->values()
            ->all();
    }
}

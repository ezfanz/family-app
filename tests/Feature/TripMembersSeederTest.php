<?php

namespace Tests\Feature;

use App\Enums\TripRole;
use App\Models\User;
use Database\Seeders\TripMembersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TripMembersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_signs_people_up_and_adds_them_to_the_trip_as_editors(): void
    {
        ['trip' => $trip] = $this->tripFixture();
        config([
            'itinerary.seed_members' => 'Amalina:Amalina1201@Example.com, Haikal:haikal@example.com',
            'itinerary.seed_member_password' => 'first-password',
        ]);

        $this->seed(TripMembersSeeder::class);

        $amalina = User::where('email', 'amalina1201@example.com')->sole();
        $this->assertSame('Amalina', $amalina->name);
        $this->assertTrue(Hash::check('first-password', $amalina->password));
        $this->assertSame(TripRole::Editor, $trip->fresh()->roleFor($amalina));
        $this->assertSame(TripRole::Editor, $trip->fresh()->roleFor(User::where('email', 'haikal@example.com')->sole()));

        // They can sign in straight away and open the trip.
        $this->post('/login', ['email' => 'Amalina1201@example.com', 'password' => 'first-password'])->assertRedirect();
        $this->getJson("/api/trips/{$trip->id}")->assertOk();
    }

    public function test_running_it_again_keeps_changed_passwords_and_roles(): void
    {
        ['trip' => $trip, 'viewer' => $viewer] = $this->tripFixture();
        config([
            'itinerary.seed_members' => "Someone:{$viewer->email}, Amira:amira@example.com",
            'itinerary.seed_member_password' => 'first-password',
        ]);
        $this->seed(TripMembersSeeder::class);

        $amira = User::where('email', 'amira@example.com')->sole();
        $amira->update(['password' => 'her-own-password']);
        $this->seed(TripMembersSeeder::class);

        $this->assertTrue(Hash::check('her-own-password', $amira->fresh()->password));
        $this->assertSame(1, User::where('email', 'amira@example.com')->count());
        $this->assertSame(TripRole::Viewer, $trip->fresh()->roleFor($viewer), 'an existing member keeps their role');
    }

    public function test_it_does_nothing_without_a_password(): void
    {
        $this->tripFixture();
        config(['itinerary.seed_members' => 'Amira:amira@example.com', 'itinerary.seed_member_password' => null]);

        $this->seed(TripMembersSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'amira@example.com']);
    }
}

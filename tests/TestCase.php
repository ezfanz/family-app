<?php

namespace Tests;

use App\Enums\TripRole;
use App\Models\Activity;
use App\Models\Day;
use App\Models\Idea;
use App\Models\ShoppingItem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment. Views render without a Vite build.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * A small trip with one member per role and one record of each kind.
     *
     * @return array{trip: Trip, owner: User, editor: User, viewer: User, outsider: User, day: Day, activity: Activity, item: ShoppingItem, idea: Idea}
     */
    protected function tripFixture(): array
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $viewer = User::factory()->create();

        $trip = Trip::factory()
            ->withMember($owner, TripRole::Owner)
            ->withMember($editor, TripRole::Editor)
            ->withMember($viewer, TripRole::Viewer)
            ->create();

        $day = Day::factory()->for($trip)->create(['day_number' => 1, 'city' => 'Osaka']);

        return [
            'trip' => $trip,
            'owner' => $owner,
            'editor' => $editor,
            'viewer' => $viewer,
            'outsider' => User::factory()->create(),
            'day' => $day,
            'activity' => Activity::factory()->create(['day_id' => $day->id, 'start_time' => '09:00', 'name' => 'Kuromon Market']),
            'item' => ShoppingItem::factory()->for($trip)->create(['name' => 'Royce Nama Chocolate']),
            'idea' => Idea::factory()->for($trip)->create(['name' => 'Tokyo Tower', 'region' => 'Tokyo']),
        ];
    }
}

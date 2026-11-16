<?php

namespace Tests\Feature;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Trip;
use Database\Seeders\TripSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_every_record_from_the_prototype(): void
    {
        $this->seed(TripSeeder::class);

        $trip = Trip::sole();

        $this->assertSame('Japan, 21 Nov – 4 Dec 2026', $trip->name);
        $this->assertSame('2026-11-21', $trip->start_date->toDateString());
        $this->assertSame('2026-12-04', $trip->end_date->toDateString());
        $this->assertSame('Asia/Tokyo', $trip->timezone);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $trip->join_code);

        $this->assertSame(14, $trip->days()->count());
        $this->assertSame(102, $trip->activities()->count());
        $this->assertSame('20:00', $trip->activities()->where('system_key', 'tax_refund')->sole()->timeLabel());
        $this->assertSame(18, $trip->shoppingItems()->count());
        $this->assertSame(29, $trip->ideas()->count());
        $this->assertSame(15, $trip->ideas()->where('region', 'Kansai')->count());
        $this->assertSame(14, $trip->ideas()->where('region', 'Tokyo')->count());
        $this->assertSame(16, $trip->activities()->where('is_halal', true)->count());
    }

    public function test_it_keeps_activity_details_exactly(): void
    {
        $this->seed(TripSeeder::class);

        $day = Trip::sole()->days()->where('day_number', 3)->sole();
        $this->assertSame('Nara', $day->city);
        $this->assertSame('Deer + temples', $day->title);
        $this->assertSame('2026-11-23', $day->date->toDateString());

        /** @var Activity $activity */
        $activity = $day->activities()->where('name', 'Nara Park, Kasuga Taisha, Nigatsu-do, Todai-ji')->sole();
        $this->assertStringStartsWith('09:00', $activity->start_time);
        $this->assertSame('Buy deer senbei. Feed the deer slowly.', $activity->notes);
        $this->assertSame(['Nara Park', 'Kasuga Taisha', 'Nigatsu-do', 'Todai-ji'], $activity->places);
        $this->assertFalse($activity->is_halal);
        $this->assertSame(ActivityStatus::Pending, $activity->status);

        $halal = $day->activities()->where('name', 'Gyumon Nara ⭐⭐⭐')->sole();
        $this->assertTrue($halal->is_halal);
        $this->assertSame('Dinner', $halal->meal);

        $item = Trip::sole()->shoppingItems()->first();
        $this->assertSame('MUJI Packable Boston Bag 23L', $item->name);
        $this->assertSame(2700, $item->estimated_yen);
    }

    public function test_it_can_run_twice_without_duplicating(): void
    {
        $this->seed(TripSeeder::class);
        $this->seed(TripSeeder::class);

        $this->assertSame(1, Trip::count());
    }
}

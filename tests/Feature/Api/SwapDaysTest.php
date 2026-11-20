<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\Day;
use App\Models\Idea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SwapDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_swapping_two_days_swaps_their_plan_but_not_their_dates(): void
    {
        ['trip' => $trip, 'editor' => $editor, 'day' => $day1, 'activity' => $kuromon] = $this->tripFixture();
        $day3 = Day::factory()->for($trip)->create(['day_number' => 3, 'date' => '2026-11-23', 'city' => 'Nara', 'title' => 'Deer + temples']);
        $day1->update(['date' => '2026-11-21', 'title' => 'Arrive + Minami']);
        $deer = Activity::factory()->create(['trip_id' => $trip->id, 'day_id' => $day3->id, 'start_time' => '09:00', 'name' => 'Nara Park']);
        $taxRefund = Activity::factory()->create(['trip_id' => $trip->id, 'day_id' => $day3->id, 'start_time' => '20:00', 'name' => 'Tax refund at customs']);
        $taxRefund->forceFill(['system_key' => 'tax_refund'])->save();
        $idea = Idea::factory()->for($trip)->create(['planned_day_id' => $day1->id]);
        $since = urlencode(now()->subSecond()->toIso8601String());

        $this->actingAs($editor)->postJson("/api/trips/{$trip->id}/days/swap", ['day_id' => $day1->id, 'with_day_id' => $day3->id])
            ->assertOk()
            ->assertJsonPath('data.0.id', $day1->id)
            ->assertJsonPath('data.0.city', 'Nara');

        $day1->refresh();
        $day3->refresh();
        $this->assertSame(['Nara', 'Deer + temples', '2026-11-21', 1], [$day1->city, $day1->title, $day1->date->toDateString(), $day1->day_number]);
        $this->assertSame(['Osaka', 'Arrive + Minami', '2026-11-23', 3], [$day3->city, $day3->title, $day3->date->toDateString(), $day3->day_number]);
        $this->assertSame($day1->id, $deer->fresh()->day_id);
        $this->assertSame($day3->id, $kuromon->fresh()->day_id);
        $this->assertSame($day3->id, $taxRefund->fresh()->day_id, 'the tax refund stop stays with departure');
        $this->assertSame($day3->id, $idea->fresh()->planned_day_id);

        // Phones pick it up from the changes feed.
        $this->actingAs($editor)->getJson("/api/trips/{$trip->id}/changes?since={$since}")
            ->assertJsonCount(2, 'days')
            ->assertJsonFragment(['id' => $deer->id, 'day_id' => $day1->id]);
    }

    public function test_only_editors_swap_days_of_their_own_trip(): void
    {
        ['trip' => $trip, 'viewer' => $viewer, 'outsider' => $outsider, 'editor' => $editor, 'day' => $day1] = $this->tripFixture();
        $day2 = Day::factory()->for($trip)->create(['day_number' => 2]);
        $otherTripDay = Day::factory()->create(['day_number' => 1]);

        $this->actingAs($viewer)->postJson("/api/trips/{$trip->id}/days/swap", ['day_id' => $day1->id, 'with_day_id' => $day2->id])->assertForbidden();
        $this->actingAs($outsider)->postJson("/api/trips/{$trip->id}/days/swap", ['day_id' => $day1->id, 'with_day_id' => $day2->id])->assertNotFound();
        $this->actingAs($editor)->postJson("/api/trips/{$trip->id}/days/swap", ['day_id' => $day1->id, 'with_day_id' => $otherTripDay->id])->assertUnprocessable();
        $this->actingAs($editor)->postJson("/api/trips/{$trip->id}/days/swap", ['day_id' => $day1->id, 'with_day_id' => $day1->id])->assertUnprocessable();
    }
}

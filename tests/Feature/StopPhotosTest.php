<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Services\StopPhotos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StopPhotosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake Wikipedia search and Commons image info.
     */
    private function fakeWikimedia(): void
    {
        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if (str_contains($request->url(), 'en.wikipedia.org')) {
                $search = $query['gsrsearch'] ?? '';
                $pages = match (true) {
                    str_contains($search, 'Osaka Castle') => [
                        ['index' => 2, 'title' => 'Osaka', 'pageimage' => 'Osaka_skyline.jpg', 'coordinates' => [['lat' => 34.69, 'lon' => 135.50]]],
                        ['index' => 1, 'title' => 'Osaka Castle', 'pageimage' => 'Osaka_Castle_02bs3200.jpg', 'coordinates' => [['lat' => 34.687, 'lon' => 135.526]]],
                    ],
                    str_contains($search, 'Dotonbori') => [
                        ['index' => 1, 'title' => 'Dōtonbori', 'pageimage' => 'Osaka_Castle_02bs3200.jpg', 'coordinates' => [['lat' => 34.668, 'lon' => 135.501]]],
                    ],
                    str_contains($search, 'Kuromon') => [
                        ['index' => 1, 'title' => 'Kuromon Market Hall (Melbourne)', 'pageimage' => 'Melbourne.jpg', 'coordinates' => [['lat' => -37.8, 'lon' => 144.9]]],
                        ['index' => 2, 'title' => 'Kuromon Market', 'pageimage' => 'NonFree_logo.png', 'coordinates' => [['lat' => 34.665, 'lon' => 135.506]]],
                    ],
                    default => [
                        ['index' => 1, 'title' => 'Some film', 'pageimage' => 'Poster.jpg', 'coordinates' => [['lat' => 35.6, 'lon' => 139.7]]],
                    ],
                };

                return Http::response(['query' => ['pages' => $pages]]);
            }

            $file = $query['titles'] ?? '';
            if ($file !== 'File:Osaka_Castle_02bs3200.jpg') {
                return Http::response(['query' => ['pages' => [['title' => $file, 'missing' => true]]]]);
            }
            $width = (int) $query['iiurlwidth'];

            return Http::response(['query' => ['pages' => [[
                'title' => $file,
                'imageinfo' => [[
                    'url' => 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Osaka_Castle_02bs3200.jpg',
                    'thumburl' => "https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Osaka_Castle_02bs3200.jpg/{$width}px-Osaka_Castle_02bs3200.jpg",
                    'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Osaka_Castle_02bs3200.jpg',
                    'extmetadata' => [
                        'Artist' => ['value' => '<a href="//commons.wikimedia.org/wiki/User:663highland">663highland</a>'],
                        'LicenseShortName' => ['value' => 'CC BY 2.5'],
                    ],
                ]],
            ]]]]);
        });
    }

    public function test_generic_stops_are_not_searched_and_names_are_cleaned(): void
    {
        $photos = new StopPhotos;

        foreach (['Arrive at KIX', 'Back to hotel', 'Lunch TBD', '7-Eleven', 'Train to Namba', 'Tax refund at customs, before check-in', 'To Shin-Osaka', 'Osaka → Kyoto Station'] as $name) {
            $this->assertSame([], $photos->queries($name, []), $name);
        }
        $this->assertSame([], $photos->queries('Arrive at KIX', ['Kansai International Airport']));
        $this->assertSame([], $photos->queries('Shinjuku → Kawaguchiko', ['Kawaguchiko Station']));
        $this->assertSame(['Osaka Castle'], $photos->queries('Osaka Castle (outside only)', []));
        $this->assertSame(['Umeda Sky Building', 'Café SKY 40'], $photos->queries('Café SKY 40 + Floating Garden ⭐', ['Umeda Sky Building']));
    }

    public function test_it_finds_a_credited_commons_photo_for_a_place_in_japan(): void
    {
        $this->fakeWikimedia();
        ['activity' => $stop] = $this->tripFixture();
        $stop->update(['name' => 'Osaka Castle (outside only)', 'places' => ['Osaka Castle']]);

        app(StopPhotos::class)->refresh($stop);

        $stop->refresh();
        $this->assertSame('wikimedia', $stop->photo_source);
        $this->assertStringContainsString('/960px-Osaka_Castle_02bs3200.jpg', $stop->photo_url);
        $this->assertStringContainsString('/330px-Osaka_Castle_02bs3200.jpg', $stop->photo_thumb_url);
        $this->assertSame('Photo: 663highland · CC BY 2.5 · Wikimedia Commons', $stop->photo_credit);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Osaka_Castle_02bs3200.jpg', $stop->photo_link);
        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', 'E-Itinerary/1.0 (Laravel trip planner)'));
    }

    public function test_titles_with_macrons_still_match(): void
    {
        $this->fakeWikimedia();
        ['activity' => $stop] = $this->tripFixture();
        $stop->update(['name' => 'Dotonbori walk + street food', 'places' => []]);

        app(StopPhotos::class)->refresh($stop);

        $this->assertSame('wikimedia', $stop->fresh()->photo_source);
    }

    public function test_wrong_matches_are_skipped(): void
    {
        $this->fakeWikimedia();
        ['activity' => $stop] = $this->tripFixture();

        // Kuromon: one result is in Australia, the other's image is not on Commons.
        $stop->update(['name' => 'Kuromon Market', 'places' => []]);
        app(StopPhotos::class)->refresh($stop);
        $this->assertNull($stop->fresh()->photo_source);
        $this->assertNotNull($stop->fresh()->photo_checked_at);

        // A title that shares no word with the stop.
        $stop->update(['name' => 'Best Halal Ramen Marhaba']);
        app(StopPhotos::class)->refresh($stop);
        $this->assertNull($stop->fresh()->photo_source);
    }

    public function test_uploads_and_removed_photos_are_kept_unless_forced(): void
    {
        $this->fakeWikimedia();
        ['activity' => $stop] = $this->tripFixture();
        $stop->update(['name' => 'Osaka Castle']);
        $stop->forceFill(['photo_source' => 'none'])->save();

        app(StopPhotos::class)->refresh($stop);
        $this->assertSame('none', $stop->fresh()->photo_source);

        app(StopPhotos::class)->refresh($stop, force: true);
        $this->assertSame('wikimedia', $stop->fresh()->photo_source);
    }

    public function test_editors_upload_and_remove_photos_and_members_can_see_them(): void
    {
        Storage::fake('receipts');
        ['trip' => $trip, 'activity' => $stop, 'editor' => $editor, 'viewer' => $viewer, 'outsider' => $outsider] = $this->tripFixture();

        $this->actingAs($viewer)->post("/api/activities/{$stop->id}/photo", ['photo' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->actingAs($editor)->post("/api/activities/{$stop->id}/photo", ['photo' => UploadedFile::fake()->image('market.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.photo.source', 'upload')
            ->assertJsonPath('data.photo.credit', "Photo: {$editor->name}");

        $path = $stop->fresh()->photo_path;
        Storage::disk('receipts')->assertExists($path);
        $this->assertStringStartsWith("trips/{$trip->id}/stops/{$stop->id}-", $path);

        $this->actingAs($viewer)->get("/api/activities/{$stop->id}/photo")->assertOk();
        $this->actingAs($outsider)->getJson("/api/activities/{$stop->id}/photo")->assertNotFound();

        $this->actingAs($editor)->deleteJson("/api/activities/{$stop->id}/photo")
            ->assertOk()
            ->assertJsonPath('data.photo', null)
            ->assertJsonPath('data.photo_removed', true);
        Storage::disk('receipts')->assertMissing($path);
    }

    public function test_adding_a_stop_looks_up_a_photo_after_the_response(): void
    {
        $this->fakeWikimedia();
        config(['itinerary.auto_photos' => true]);
        ['day' => $day, 'editor' => $editor] = $this->tripFixture();

        $id = $this->actingAs($editor)->postJson('/api/activities', [
            'day_id' => $day->id,
            'time' => '11:00',
            'name' => 'Osaka Castle (outside only)',
            'places' => ['Osaka Castle'],
        ])->assertCreated()->json('data.id');

        $this->assertSame('wikimedia', Activity::find($id)->photo_source);
    }

    public function test_the_backfill_command_checks_each_stop_once(): void
    {
        $this->fakeWikimedia();
        ['activity' => $stop] = $this->tripFixture();
        $stop->update(['name' => 'Osaka Castle']);

        $this->artisan('stops:find-photos', ['--pause' => 0])->expectsOutputToContain('found 1 photos')->assertSuccessful();
        $this->assertSame('wikimedia', $stop->fresh()->photo_source);

        $this->artisan('stops:find-photos', ['--pause' => 0])->expectsOutputToContain('Checked 0 stops')->assertSuccessful();
    }
}

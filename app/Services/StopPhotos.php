<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds a photo for an itinerary stop on Wikipedia / Wikimedia Commons.
 *
 * A Wikipedia page only counts when it has map coordinates inside Japan, shares
 * a real word with what we searched for, and its main image lives on Commons
 * (so it is openly licensed and can be credited). Everything else is skipped:
 * a wrong photo is worse than none.
 */
class StopPhotos
{
    /** Stops that are about getting somewhere or eating, not a place worth a photo. */
    private const GENERIC = '/^(arrive|arrival|train|bus|taxi|walk|back to|return|check[ -]?(in|out)|drop|pick ?up|breakfast|lunch|dinner|snack|free (time|day)|rest|sleep|shopping$|flight|fly|depart|departure|tax refund|hotel|7-?eleven|lawson|family ?mart|to)\b|\btbd\b|\btbc\b|→/iu';

    private const STOP_WORDS = ['the', 'and', 'for', 'with', 'from', 'only', 'outside', 'inside', 'near', 'street', 'food', 'walk', 'tour', 'visit', 'japan'];

    /**
     * Find a photo for a stop and save it on the stop. Uploaded or removed photos are
     * left alone unless forced. Network errors are logged and never reach the user.
     */
    public function refresh(Activity $activity, bool $force = false): Activity
    {
        if (! $force && in_array($activity->photo_source, ['upload', 'none'], true)) {
            return $activity;
        }

        try {
            $photo = $this->find($activity->name, $activity->places ?? [], $activity->day?->city);
        } catch (Throwable $error) {
            Log::warning('Stop photo lookup failed', ['activity' => $activity->id, 'error' => $error->getMessage()]);

            return $activity;
        }

        if ($activity->photo_source === 'upload' && $photo === null) {
            return $activity;
        }

        $activity->forceFill($photo === null
            ? ['photo_source' => null, 'photo_url' => null, 'photo_thumb_url' => null, 'photo_credit' => null, 'photo_link' => null]
            : ['photo_source' => 'wikimedia', ...$photo]);
        $activity->photo_checked_at = now();
        $activity->save();

        return $activity;
    }

    /**
     * @param  list<string>  $places
     * @return array{photo_url: string, photo_thumb_url: string, photo_credit: string, photo_link: string}|null
     */
    public function find(string $name, array $places, ?string $city): ?array
    {
        foreach ($this->queries($name, $places) as $query) {
            $page = $this->bestPage($query, $city);
            if ($page === null) {
                continue;
            }
            $photo = $this->commonsPhoto($page['pageimage']);
            if ($photo !== null) {
                return $photo;
            }
        }

        return null;
    }

    /**
     * What to search for: the stop's named places first, then its name, without
     * brackets, emoji or "+ second thing". Generic stops get no search at all.
     *
     * @param  list<string>  $places
     * @return list<string>
     */
    public function queries(string $name, array $places): array
    {
        $clean = fn (string $text): string => trim((string) preg_replace(['/\(.*?\)/u', '/[^\p{L}\p{N}\s\'\-&.]/u', '/\s+/u'], ['', ' ', ' '], explode('+', $text)[0]));

        // Getting somewhere or eating: no photo, even if its places are famous ("Arrive at KIX").
        if (preg_match(self::GENERIC, trim($name))) {
            return [];
        }

        $queries = [];
        foreach (array_slice($places, 0, 2) as $place) {
            if (! preg_match(self::GENERIC, trim($place))) {
                $queries[] = $clean($place);
            }
        }
        $queries[] = $clean($name);

        return array_values(array_unique(array_filter($queries, fn (string $q): bool => mb_strlen($q) >= 3)));
    }

    /**
     * First search result that is a real place in Japan, matches the query and has an image.
     *
     * @return array{title: string, pageimage: string}|null
     */
    private function bestPage(string $query, ?string $city): ?array
    {
        $search = $city !== null && ! Str::contains(mb_strtolower($query), mb_strtolower($city)) ? "{$query} {$city}" : $query;
        $response = $this->http()->get((string) config('itinerary.wikipedia_api'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'generator' => 'search',
            'gsrsearch' => $search,
            'gsrnamespace' => 0,
            'gsrlimit' => 5,
            'prop' => 'coordinates|pageimages',
            'piprop' => 'name',
        ])->throw()->json();

        $pages = collect($response['query']['pages'] ?? [])->sortBy('index');
        foreach ($pages as $page) {
            $coordinates = $page['coordinates'][0] ?? null;
            if (empty($page['pageimage']) || $coordinates === null) {
                continue;
            }
            if (! $this->inJapan((float) $coordinates['lat'], (float) $coordinates['lon'])) {
                continue;
            }
            if (! $this->sharesAWord($query, (string) $page['title'])) {
                continue;
            }

            return ['title' => (string) $page['title'], 'pageimage' => (string) $page['pageimage']];
        }

        return null;
    }

    /**
     * Photo and credit from Wikimedia Commons; null when the file is not on Commons
     * (for example a non-free image kept only on English Wikipedia).
     *
     * @return array{photo_url: string, photo_thumb_url: string, photo_credit: string, photo_link: string}|null
     */
    private function commonsPhoto(string $file): ?array
    {
        $large = $this->imageInfo($file, 960);
        if ($large === null) {
            return null;
        }
        $small = $this->imageInfo($file, 330);

        $meta = $large['extmetadata'] ?? [];
        $artist = trim(html_entity_decode(strip_tags((string) ($meta['Artist']['value'] ?? '')), ENT_QUOTES));
        $license = trim(strip_tags((string) ($meta['LicenseShortName']['value'] ?? '')));
        $credit = collect(['Photo: '.($artist !== '' ? Str::limit($artist, 80) : 'Wikimedia Commons'), $license, 'Wikimedia Commons'])
            ->filter()
            ->unique()
            ->implode(' · ');

        return [
            'photo_url' => (string) ($large['thumburl'] ?? $large['url']),
            'photo_thumb_url' => (string) ($small['thumburl'] ?? $large['thumburl'] ?? $large['url']),
            'photo_credit' => Str::limit($credit, 290),
            'photo_link' => (string) ($large['descriptionurl'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function imageInfo(string $file, int $width): ?array
    {
        $response = $this->http()->get((string) config('itinerary.commons_api'), [
            'action' => 'query',
            'format' => 'json',
            'formatversion' => 2,
            'titles' => 'File:'.$file,
            'prop' => 'imageinfo',
            'iiprop' => 'url|extmetadata',
            'iiurlwidth' => $width,
            'iiextmetadatafilter' => 'Artist|LicenseShortName',
        ])->throw()->json();

        $page = $response['query']['pages'][0] ?? null;
        if ($page === null || ! empty($page['missing']) || empty($page['imageinfo'][0])) {
            return null;
        }

        return $page['imageinfo'][0];
    }

    private function inJapan(float $lat, float $lon): bool
    {
        return $lat >= 20 && $lat <= 46 && $lon >= 122 && $lon <= 154;
    }

    /**
     * True when the page title and the query share a real word. Accents are folded,
     * so "Dōtonbori" matches "Dotonbori".
     */
    private function sharesAWord(string $query, string $title): bool
    {
        $words = fn (string $text): array => array_diff(
            array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower(Str::ascii($text))) ?: [], fn (string $w): bool => mb_strlen($w) >= 3),
            self::STOP_WORDS,
        );

        return array_intersect($words($query), $words($title)) !== [];
    }

    private function http(): PendingRequest
    {
        // Wikimedia rate-limits busy clients (429): wait and try again a couple of times.
        return Http::withUserAgent((string) config('itinerary.user_agent'))
            ->acceptJson()
            ->timeout(8)
            ->retry(3, fn (int $attempt): int => $attempt * 2000, fn (Throwable $e): bool => $e instanceof RequestException && $e->response->status() === 429, throw: false);
    }
}

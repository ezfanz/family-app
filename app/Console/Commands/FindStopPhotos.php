<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Services\StopPhotos;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('stops:find-photos {--trip= : Only this trip id} {--again : Also re-check stops already looked up (uploads and removed photos are kept)} {--pause=1000 : Milliseconds between stops, to stay under Wikimedia rate limits}')]
#[Description('Find Wikimedia Commons photos for itinerary stops')]
class FindStopPhotos extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(StopPhotos $photos): int
    {
        $stops = Activity::query()
            ->with('day')
            ->when($this->option('trip'), fn ($query, $trip) => $query->where('trip_id', (int) $trip))
            ->where(fn ($query) => $query->whereNull('photo_source')->orWhere('photo_source', 'wikimedia'))
            ->when(! $this->option('again'), fn ($query) => $query->whereNull('photo_checked_at'))
            ->orderBy('trip_id')
            ->get();

        $found = 0;
        $this->withProgressBar($stops, function (Activity $stop) use ($photos, &$found): void {
            $photos->refresh($stop);
            $found += $stop->photo_source === 'wikimedia' ? 1 : 0;
            usleep(max(0, (int) $this->option('pause')) * 1000); // stay under Wikimedia rate limits
        });
        $this->newLine();
        $this->info("Checked {$stops->count()} stops, found {$found} photos.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Notifications;

use App\Models\StopComment;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * "Ipan mentioned you on Kuromon Market · Day 1 Osaka · 10:30", shown under
 * the bell in the app. Stored in the database only.
 */
class MentionedInComment extends Notification
{
    public function __construct(public StopComment $comment, public User $actor) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{trip_id: int, day_id: int, day_number: int, city: string|null, activity_id: string, stop_name: string, time: string, comment_id: string, actor_id: int, actor_name: string, excerpt: string}
     */
    public function toArray(object $notifiable): array
    {
        $stop = $this->comment->activity;
        $day = $stop->day;

        return [
            'trip_id' => $day->trip_id,
            'day_id' => $day->id,
            'day_number' => $day->day_number,
            'city' => $day->city,
            'activity_id' => $stop->id,
            'stop_name' => $stop->name,
            'time' => $stop->timeLabel(),
            'comment_id' => $this->comment->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'excerpt' => Str::limit($this->comment->body, 140),
        ];
    }
}

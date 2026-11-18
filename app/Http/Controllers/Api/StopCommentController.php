<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStopCommentRequest;
use App\Http\Resources\StopCommentResource;
use App\Models\Activity;
use App\Models\StopComment;
use App\Notifications\MentionedInComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class StopCommentController extends Controller
{
    /**
     * Post a comment on a stop. People @mentioned in it (trip members other
     * than the author) get one bell notification. Safe to retry with the same id.
     */
    public function store(StoreStopCommentRequest $request, Activity $activity): JsonResponse
    {
        $trip = $activity->trip;
        Gate::authorize('participate', $trip);
        $user = $request->user();

        $id = $request->validated('id');
        if ($id !== null) {
            $existing = StopComment::withTrashed()->whereKey($id)->where('user_id', $user->id)->first();
            if ($existing !== null) {
                return StopCommentResource::make($existing)->response()->setStatusCode(200);
            }
        }

        $memberIds = $trip->members()->pluck('users.id')->all();
        $mentionIds = collect($request->validated('mention_ids') ?? [])
            ->map(fn ($mentionId): int => (int) $mentionId)
            ->filter(fn (int $mentionId): bool => $mentionId !== $user->id && in_array($mentionId, $memberIds, true))
            ->unique()
            ->values()
            ->all();

        $comment = new StopComment([
            'body' => $request->validated('body'),
            'mention_ids' => $mentionIds === [] ? null : $mentionIds,
        ]);
        if ($id !== null) {
            $comment->id = $id;
        }
        $comment->activity_id = $activity->id;
        $comment->user_id = $user->id;
        $comment->save();

        if ($mentionIds !== []) {
            Notification::send($trip->members()->whereIn('users.id', $mentionIds)->get(), new MentionedInComment($comment, $user));
        }

        return StopCommentResource::make($comment)->response()->setStatusCode(201);
    }

    /**
     * Delete your own comment.
     */
    public function destroy(StopComment $comment): Response
    {
        Gate::authorize('participate', $comment->activity->trip);
        abort_unless($comment->user_id === request()->user()->id, 403, 'You can only delete your own comments.');

        $comment->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Enums;

enum TripRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * Role given to someone who joins with the join code (Tabi: "New people join as Viewer").
     */
    public static function forNewMembers(): self
    {
        return self::Viewer;
    }

    /**
     * Roles an owner can hand out to other members.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Editor, self::Viewer];
    }

    /**
     * Whether this role may change trip content.
     */
    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }
}

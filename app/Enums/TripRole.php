<?php

namespace App\Enums;

enum TripRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * Role given to someone who joins with the join code.
     * Editor matches the prototype, where everyone in the group could edit.
     */
    public static function forNewMembers(): self
    {
        return self::Editor;
    }

    /**
     * Whether this role may change trip content.
     */
    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }
}

<?php

namespace App\Enums;

enum TripRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * Whether this role may change trip content.
     */
    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }
}

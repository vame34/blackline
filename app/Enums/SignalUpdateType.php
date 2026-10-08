<?php

namespace App\Enums;

enum SignalUpdateType: string
{
    case Published = 'published';
    case Entry = 'entry';
    case LevelUpdate = 'level_update';
    case Canceled = 'canceled';
    case Closed = 'closed';
    case Note = 'note';
}

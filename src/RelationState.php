<?php

declare(strict_types=1);

namespace WilyCode\Database\Model;

enum RelationState: string
{
    case Unloaded = 'unloaded';
    case Reference = 'reference';
    case Loaded = 'loaded';
}

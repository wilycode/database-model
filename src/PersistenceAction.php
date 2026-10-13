<?php

declare(strict_types=1);

namespace WilyCode\Database\Model;

enum PersistenceAction: string
{
    case Insert = 'insert';
    case Update = 'update';
    case None = 'none';
}

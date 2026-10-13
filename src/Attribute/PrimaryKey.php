<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class PrimaryKey extends Property
{
}
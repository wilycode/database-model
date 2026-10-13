<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Repository
{
    private ?string $className = null;

    public function __construct(?string $className = null)
    {
        $this->className = $className;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

}
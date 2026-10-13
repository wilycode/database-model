<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use Attribute;
use ReflectionType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Property
{
    private ?string $dbName = null;
    private ?string $setterMethod = null;
    private ?string $getterMethod = null;

    private ?string $enumClass = null;

    public function __construct(?string $dbName = null)
    {
        $this->dbName = $dbName;
    }

    public function setDbName(?string $dbName): void
    {
        $this->dbName = $dbName;
    }

    public function getDbName(): ?string
    {
        return $this->dbName;
    }

    public function setSetterMethod(string $name): void
    {
        $this->setterMethod = $name;
    }

    public function getSetterMethod(): ?string
    {
        return $this->setterMethod;
    }

    public function getEnumClass(): ?string
    {
        return $this->enumClass;
    }

    public function setEnumClass(?string $enumClass): void
    {
        $this->enumClass = $enumClass;
    }

    public function isEnumClass(): bool
    {
        return $this->enumClass !== null;
    }

    public function getGetterMethod(): ?string
    {
        return $this->getterMethod;
    }

    public function setGetterMethod(?string $getterMethod): void
    {
        $this->getterMethod = $getterMethod;
    }


    private ?ReflectionType $phpType = null;

    public function setPhpType(?ReflectionType $phpType): void
    {
        $this->phpType = $phpType;
    }

    public function getPhpType(): ?ReflectionType
    {
        return $this->phpType;
    }


}
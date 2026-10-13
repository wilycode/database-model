<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use RuntimeException;
use WilyCode\Database\Model\Model;

abstract class Has
{
    private ?string $tableName = null;
    private ?string $tableAlias = null;
    private ?string $modelName = null;
    private ?string $setterMethod = null;
    private ?string $getterMethod = null;
    private ?string $onCondition = null;
    private ?string $foreignKey = null;

    public function __construct(
        ?string $onCondition = null,
        ?string $setterMethod = null,
        ?string $getterMethod = null,
        ?string $foreignKey = null,
        ?string $tableAlias = null,
    ) {
        $this->onCondition = $onCondition;
        $this->setterMethod = $setterMethod;
        $this->getterMethod = $getterMethod;
        $this->foreignKey = $foreignKey;
        $this->tableAlias = $tableAlias;
    }

    public function setTableName(?string $tableName): void
    {
        $this->tableName = $tableName;
    }

    public function getTableName(): ?string
    {
        return $this->tableName;
    }

    public function getModelName(): ?string
    {
        return $this->modelName;
    }

    public function setModelName(?string $modelName): void
    {
        $this->modelName = $modelName;
    }

    public function getTableAlias(): ?string
    {
        return $this->tableAlias;
    }

    public function setTableAlias(?string $tableAlias): void
    {
        $this->tableAlias = $tableAlias;
    }


    public function setSetterMethod(string $name): void
    {
        $this->setterMethod = $name;
    }

    public function getSetterMethod(): ?string
    {
        return $this->setterMethod;
    }

    public function getGetterMethod(): ?string
    {
        return $this->getterMethod;
    }

    public function setGetterMethod(?string $getterMethod): void
    {
        $this->getterMethod = $getterMethod;
    }

    public function getOnCondition(): ?string
    {
        return $this->onCondition;
    }

    public function setOnCondition(?string $onCondition): void
    {
        $this->onCondition = $onCondition;
    }

    public function getForeignKey(): ?string
    {
        return $this->foreignKey;
    }

    public function setForeignKey(?string $foreignKey): void
    {
        $this->foreignKey = $foreignKey;
    }

    /** @param array<string, mixed> $data */
    public function newModelInstance(array $data = []): Model
    {
        $modelName = $this->getModelName();
        if ($modelName === null) {
            throw new RuntimeException('Relationship modelName is not configured.');
        }
        return new $modelName($data);
}

}
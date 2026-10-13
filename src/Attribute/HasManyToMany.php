<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasManyToMany extends Has
{
    private ?string $linkingTable = null;
    private ?string $linkingColumn = null;

    public function __construct(
        ?string $modelName = null,
        ?string $onCondition = null,
        ?string $setterMethod = null,
        ?string $getterMethod = null,
        ?string $foreignKey = null,
        ?string $linkingTable = null,
        ?string $linkingColumn = null,
        ?string $tableAlias = null,
    ) {
        parent::__construct(
            onCondition: $onCondition,
            setterMethod: $setterMethod,
            getterMethod: $getterMethod,
            foreignKey: $foreignKey,
            tableAlias: $tableAlias,
        );
        $this->setModelName($modelName);
        $this->linkingTable = $linkingTable;
        $this->linkingColumn = $linkingColumn;
    }

    public function getLinkingColumn(): ?string
    {
        return $this->linkingColumn;
    }

    public function setLinkingColumn(?string $linkingColumn): void
    {
        $this->linkingColumn = $linkingColumn;
    }

    public function getLinkingTable(): ?string
    {
        return $this->linkingTable;
    }

    public function setLinkingTable(?string $linkingTable): void
    {
        $this->linkingTable = $linkingTable;
    }

}
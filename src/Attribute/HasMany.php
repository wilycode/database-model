<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany extends Has
{
    public function __construct(
        ?string $modelName = null,
        ?string $onCondition = null,
        ?string $setterMethod = null,
        ?string $getterMethod = null,
        ?string $foreignKey = null,
        ?string $tableAlias = null,
    )
    {
        parent::__construct(
            onCondition: $onCondition,
            setterMethod: $setterMethod,
            getterMethod: $getterMethod,
            foreignKey: $foreignKey,
            tableAlias: $tableAlias,
        );
        $this->setModelName($modelName);
    }
}
<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Metadata;

use WilyCode\Database\Model\Attribute\HasMany;
use WilyCode\Database\Model\Attribute\HasManyToMany;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\Property;

/** @internal */
final readonly class ModelMetadataDefinition
{
    /**
     * @param array<string, Property> $properties
     * @param array<string, string> $dbNameToPropertyNameMap
     * @param array<string, HasOne|HasMany|HasManyToMany> $relationships
     * @param list<string> $foreignKeys
     */
    public function __construct(
        public string $tableName,
        public ?string $tableAlias,
        public ?string $repositoryName,
        public array $properties,
        public array $dbNameToPropertyNameMap,
        public array $relationships,
        public string $primaryKey,
        public array $foreignKeys,
    ) {
    }
}

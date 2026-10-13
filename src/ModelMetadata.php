<?php

declare(strict_types=1);

namespace WilyCode\Database\Model;

use InvalidArgumentException;
use WilyCode\Database\Model\Attribute\HasMany;
use WilyCode\Database\Model\Attribute\HasManyToMany;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Metadata\ModelMetadataFactory;

final class ModelMetadata
{
    /** @var array<class-string<Model>, self> */
    private static array $cache = [];

    private string $tableName;
    private ?string $tableAlias;
    private ?string $repositoryName;

    /** @var array<string, Property> */
    private array $properties;

    /** @var array<string, string> */
    private array $dbNameToPropertyNameMap;

    /** @var array<string, HasOne|HasMany|HasManyToMany> */
    private array $relationships;

    private string $primaryKey;

    /** @var list<string> */
    private array $foreignKeys;

    /** @param class-string<Model> $modelClass */
    public static function for(string $modelClass): self
    {
        if (!class_exists($modelClass)) {
            throw new InvalidArgumentException(sprintf('Model class "%s" does not exist.', $modelClass));
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" must extend %s.', $modelClass, Model::class));
        }

        return self::$cache[$modelClass] ??= new self($modelClass);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /** @param class-string<Model> $modelClass */
    private function __construct(string $modelClass)
    {
        $definition = new ModelMetadataFactory()->create($modelClass);

        $this->tableName = $definition->tableName;
        $this->tableAlias = $definition->tableAlias;
        $this->repositoryName = $definition->repositoryName;
        $this->properties = $definition->properties;
        $this->dbNameToPropertyNameMap = $definition->dbNameToPropertyNameMap;
        $this->relationships = $definition->relationships;
        $this->primaryKey = $definition->primaryKey;
        $this->foreignKeys = $definition->foreignKeys;
    }

    public function mapDbNameToPropertyName(string $dbName): ?string
    {
        return $this->dbNameToPropertyNameMap[$dbName] ?? null;
    }

    public function getPrimaryKeyPropertyName(): string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKeyDbName(): string
    {
        return $this->getProperty($this->getPrimaryKeyPropertyName())->getDbName();
    }

    public function getPrimaryKeyProperty(): Property
    {
        return $this->getProperty($this->getPrimaryKeyPropertyName());
    }

    public function getPrimaryKeyGetterMethod(): string
    {
        $getter = $this->getPrimaryKeyProperty()->getGetterMethod();

        if ($getter === null) {
            throw new \RuntimeException('Primary key getter is not configured.');
        }

        return $getter;
    }

    public function getForeignKeyDbName(string $name): string
    {
        if (!$this->hasForeignKey($name)) {
            throw new InvalidArgumentException(sprintf('The foreign key "%s" does not exist.', $name));
        }

        return $this->getProperty($name)->getDbName();
    }

    public function hasForeignKey(string $name): bool
    {
        return in_array($name, $this->foreignKeys, true);
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getTableAlias(): ?string
    {
        return $this->tableAlias;
    }

    public function hasProperty(string $name): bool
    {
        return array_key_exists($name, $this->properties);
    }

    public function getProperty(string $name): Property
    {
        if (!$this->hasProperty($name)) {
            throw new InvalidArgumentException(sprintf('Mapped property "%s" does not exist.', $name));
        }

        return $this->properties[$name];
    }

    /** @return array<string, Property> */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /** @return list<string> */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    public function getDbPropertyName(string $name): string
    {
        if (!array_key_exists($name, $this->properties)) {
            throw new InvalidArgumentException('Property name "' . $name . '" does not exist.');
        }
        return $this->properties[$name]->getDbName();
    }

    /**
     * Returns every mapped database column, including scalar foreign keys that
     * also expose a HasOne relationship.
     *
     * @return array<string, string>
     */
    public function getScalarDbProperties(): array
    {
        $dbNames = [];
        foreach ($this->properties as $name => $property) {
            $dbNames[$name] = (string) $property->getDbName();
        }

        return $dbNames;
    }

    public function getRelationship(string $name): HasOne|HasMany|HasManyToMany
    {
        if (!$this->hasRelationship($name)) {
            throw new InvalidArgumentException(sprintf('Relationship "%s" does not exist.', $name));
        }

        return $this->relationships[$name];
    }

    public function hasRelationship(string $name): bool
    {
        return array_key_exists($name, $this->relationships);
    }

    /** @return array<string, HasOne|HasMany|HasManyToMany> */
    public function getRelationships(): array
    {
        return $this->relationships;
    }

    public function getRepositoryName(): string
    {
        if ($this->repositoryName === null) {
            throw new \RuntimeException(
                'Repository is not configured for this model. Add #[Repository(YourRepository::class)] on the model class.'
            );
        }

        return $this->repositoryName;
    }
}

<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Metadata;

use LogicException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use WilyCode\Database\Model\Attribute\ForeignKey;
use WilyCode\Database\Model\Attribute\Has;
use WilyCode\Database\Model\Attribute\HasMany;
use WilyCode\Database\Model\Attribute\HasManyToMany;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\PrimaryKey;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Attribute\Repository;
use WilyCode\Database\Model\Attribute\Table;
use WilyCode\Database\Model\Model;

/**
 * Builds validated model metadata from PHP attributes and reflection.
 *
 * @internal
 */
final class ModelMetadataFactory
{
    /** @param class-string<Model> $modelClass */
    public function create(string $modelClass): ModelMetadataDefinition
    {
        $reflectionClass = new ReflectionClass($modelClass);
        $table = $this->resolveTableAttribute($reflectionClass);
        $tableName = (string) $table->getName();
        $repositoryName = $this->resolveRepositoryName($reflectionClass);

        /** @var array<string, Property> $properties */
        $properties = [];
        /** @var array<string, string> $dbNameToPropertyNameMap */
        $dbNameToPropertyNameMap = [];
        /** @var array<string, HasOne|HasMany|HasManyToMany> $relationships */
        $relationships = [];
        /** @var list<string> $foreignKeys */
        $foreignKeys = [];
        $primaryKey = null;

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $primaryKeyAttrs = $property->getAttributes(PrimaryKey::class);
            $foreignKeyAttrs = $property->getAttributes(ForeignKey::class);
            $propertyAttrs = $property->getAttributes(Property::class);

            $mappingAttributeCount = count($primaryKeyAttrs) + count($foreignKeyAttrs) + count($propertyAttrs);
            if ($mappingAttributeCount > 1) {
                throw new LogicException(sprintf(
                    'Property "%s::$%s" must define exactly one mapping attribute.',
                    $reflectionClass->getName(),
                    $propertyName,
                ));
            }

            if ($primaryKeyAttrs !== []) {
                if ($primaryKey !== null) {
                    throw new LogicException(sprintf(
                        'Model "%s" defines more than one primary key.',
                        $reflectionClass->getName(),
                    ));
                }

                /** @var PrimaryKey $primaryKeyAttribute */
                $primaryKeyAttribute = $primaryKeyAttrs[0]->newInstance();
                $primaryKey = $propertyName;
                $this->addProperty(
                    propertyAttribute: $primaryKeyAttribute,
                    property: $property,
                    foreignKeys: $foreignKeys,
                    properties: $properties,
                    dbNameToPropertyNameMap: $dbNameToPropertyNameMap,
                );
            } elseif ($foreignKeyAttrs !== []) {
                /** @var ForeignKey $foreignKeyAttribute */
                $foreignKeyAttribute = $foreignKeyAttrs[0]->newInstance();
                $foreignKeys[] = $propertyName;
                $this->addProperty(
                    propertyAttribute: $foreignKeyAttribute,
                    property: $property,
                    foreignKeys: $foreignKeys,
                    properties: $properties,
                    dbNameToPropertyNameMap: $dbNameToPropertyNameMap,
                );
            } elseif ($propertyAttrs !== []) {
                /** @var Property $modelProperty */
                $modelProperty = $propertyAttrs[0]->newInstance();
                $this->addProperty(
                    propertyAttribute: $modelProperty,
                    property: $property,
                    foreignKeys: $foreignKeys,
                    properties: $properties,
                    dbNameToPropertyNameMap: $dbNameToPropertyNameMap,
                );
            }

            $hasOneAttrs = $property->getAttributes(HasOne::class);
            $hasManyAttrs = $property->getAttributes(HasMany::class);
            $manyToManyAttrs = $property->getAttributes(HasManyToMany::class);

            $relationshipAttributeCount = count($hasOneAttrs) + count($hasManyAttrs) + count($manyToManyAttrs);
            if ($relationshipAttributeCount > 1) {
                throw new LogicException(sprintf(
                    'Property "%s::$%s" must define exactly one relationship attribute.',
                    $reflectionClass->getName(),
                    $propertyName,
                ));
            }

            if ($hasOneAttrs !== []) {
                /** @var HasOne $hasOne */
                $hasOne = $hasOneAttrs[0]->newInstance();
                $type = $property->getType();

                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    throw new LogicException(sprintf(
                        'HasOne relationship "%s::$%s" must use a model class as its property type.',
                        $reflectionClass->getName(),
                        $propertyName,
                    ));
                }

                $hasOne->setModelName($type->getName());
                $this->prepareHas($hasOne, $reflectionClass, $property);
                $setter = $hasOne->getSetterMethod() ?? ('set' . ucfirst($propertyName));
                if (!$reflectionClass->hasMethod($setter) || !$reflectionClass->getMethod($setter)->isPublic()) {
                    throw new LogicException(sprintf(
                        'Relationship "%s::$%s" requires public method %s().',
                        $reflectionClass->getName(),
                        $propertyName,
                        $setter,
                    ));
                }
                $hasOne->setSetterMethod($setter);
                $relationships[$propertyName] = $hasOne;

                if (in_array($propertyName, $foreignKeys, true)) {
                    $foreignKeyName = $properties[$propertyName]->getDbName();
                    if ($hasOne->getForeignKey() === null) {
                        $hasOne->setForeignKey($foreignKeyName);
                    } elseif ($hasOne->getForeignKey() !== $foreignKeyName) {
                        throw new LogicException(sprintf(
                            'Relationship "%s::$%s" defines conflicting foreign key names.',
                            $reflectionClass->getName(),
                            $propertyName,
                        ));
                    }
                }
            }

            if ($hasManyAttrs !== []) {
                /** @var HasMany $hasMany */
                $hasMany = $hasManyAttrs[0]->newInstance();
                $this->prepareHas($hasMany, $reflectionClass, $property);
                $this->configureCollectionSetter($reflectionClass, $hasMany, $propertyName);
                $relationships[$propertyName] = $hasMany;
            }

            if ($manyToManyAttrs !== []) {
                /** @var HasManyToMany $manyToMany */
                $manyToMany = $manyToManyAttrs[0]->newInstance();
                $this->prepareHas($manyToMany, $reflectionClass, $property);
                $manyToMany->setLinkingTable($manyToMany->getLinkingTable() ?? ($tableName . '_' . $manyToMany->getTableName()));
                $manyToMany->setLinkingColumn($manyToMany->getLinkingColumn() ?? ($tableName . '_id'));
                $manyToMany->setForeignKey($manyToMany->getForeignKey() ?? ($manyToMany->getTableName() . '_id'));
                $this->configureCollectionSetter($reflectionClass, $manyToMany, $propertyName);
                $relationships[$propertyName] = $manyToMany;
            }
        }

        if ($primaryKey === null) {
            throw new LogicException(sprintf(
                'Model "%s" must define a primary key.',
                $reflectionClass->getName(),
            ));
        }

        return new ModelMetadataDefinition(
            tableName: $tableName,
            tableAlias: $table->getAlias(),
            repositoryName: $repositoryName,
            properties: $properties,
            dbNameToPropertyNameMap: $dbNameToPropertyNameMap,
            relationships: $relationships,
            primaryKey: $primaryKey,
            foreignKeys: $foreignKeys,
        );
    }

    /**
     * @param ReflectionClass<Model> $reflectionClass
     */
    private function resolveTableAttribute(ReflectionClass $reflectionClass): Table
    {
        $tableAttributes = $reflectionClass->getAttributes(Table::class);
        if ($tableAttributes === []) {
            throw new LogicException('The "Table" attribute is required for this model');
        }

        /** @var Table $table */
        $table = $tableAttributes[0]->newInstance();

        if ($table->getName() === null) {
            $table->setName($this->camelCaseToDbName($reflectionClass->getShortName()));
        }

        if ($table->getAlias() === null || $table->getAlias() === '') {
            $table->setAlias(Table::createAliasByModelName($reflectionClass->getName()));
        }

        return $table;
    }

    /**
     * @param ReflectionClass<Model> $reflectionClass
     */
    private function resolveRepositoryName(ReflectionClass $reflectionClass): ?string
    {
        $repositoryAttributes = $reflectionClass->getAttributes(Repository::class);
        if ($repositoryAttributes === []) {
            return null;
        }

        /** @var Repository $repository */
        $repository = $repositoryAttributes[0]->newInstance();
        $repositoryName = $repository->getClassName();

        if ($repositoryName === null) {
            throw new \RuntimeException(sprintf(
                'Model "%s" has #[Repository] but no repository class was provided.',
                $reflectionClass->getName(),
            ));
        }

        return $repositoryName;
    }

    /**
     * @param list<string> $foreignKeys
     * @param array<string, Property> $properties
     * @param array<string, string> $dbNameToPropertyNameMap
     */
    private function addProperty(
        Property $propertyAttribute,
        ReflectionProperty $property,
        array $foreignKeys,
        array &$properties,
        array &$dbNameToPropertyNameMap,
    ): void {
        if ($propertyAttribute->getDbName() === null) {
            $dbName = $this->camelCaseToDbName($property->getName());

            if (in_array($property->getName(), $foreignKeys, true)) {
                $propertyAttribute->setDbName(str_ends_with($dbName, '_id') ? $dbName : $dbName . '_id');
            } else {
                $propertyAttribute->setDbName($dbName);
            }
        }

        $type = $property->getType();
        if ($type === null) {
            throw new LogicException(sprintf(
                'Mapped property "%s::$%s" must declare a PHP type.',
                $property->getDeclaringClass()->getName(),
                $property->getName(),
            ));
        }

        $propertyAttribute->setPhpType($type);

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $reflectionClassValue = new ReflectionClass($type->getName());
            if ($reflectionClassValue->isEnum()) {
                $propertyAttribute->setEnumClass($type->getName());
            }
        }

        $setter = 'set' . ucfirst($property->getName());
        $getter = 'get' . ucfirst($property->getName());
        $declaringClass = $property->getDeclaringClass();

        if (!$declaringClass->hasMethod($setter) || !$declaringClass->getMethod($setter)->isPublic()) {
            throw new LogicException(sprintf(
                'Mapped property "%s::$%s" requires public method %s().',
                $declaringClass->getName(),
                $property->getName(),
                $setter,
            ));
        }

        if (!$declaringClass->hasMethod($getter) || !$declaringClass->getMethod($getter)->isPublic()) {
            throw new LogicException(sprintf(
                'Mapped property "%s::$%s" requires public method %s().',
                $declaringClass->getName(),
                $property->getName(),
                $getter,
            ));
        }

        $dbName = (string) $propertyAttribute->getDbName();
        if (array_key_exists($dbName, $dbNameToPropertyNameMap)) {
            throw new LogicException(sprintf(
                'Mapped properties "%s" and "%s" use the same database column "%s".',
                $dbNameToPropertyNameMap[$dbName],
                $property->getName(),
                $dbName,
            ));
        }

        $propertyAttribute->setSetterMethod($setter);
        $propertyAttribute->setGetterMethod($getter);
        $properties[$property->getName()] = $propertyAttribute;
        $dbNameToPropertyNameMap[(string) $propertyAttribute->getDbName()] = $property->getName();
    }

    /** @param ReflectionClass<Model> $ownerClass */
    private function prepareHas(Has $has, ReflectionClass $ownerClass, ReflectionProperty $property): void
    {
        $name = $property->getName();

        if ($has->getModelName() === null) {
            throw new LogicException(sprintf(
                'Relationship "%s::$%s" requires "modelName". For HasMany/HasManyToMany, pass modelName: Foo::class.',
                $ownerClass->getName(),
                $name,
            ));
        }

        if (!is_subclass_of($has->getModelName(), Model::class)) {
            throw new LogicException(sprintf(
                'Relationship "%s::$%s" must reference a subclass of %s, got "%s".',
                $ownerClass->getName(),
                $name,
                Model::class,
                $has->getModelName(),
            ));
        }

        if ($has->getTableName() === null) {
            $relatedClass = new ReflectionClass($has->getModelName());
            $relatedTable = $this->resolveTableAttribute($relatedClass);
            $has->setTableName($relatedTable->getName());
        }

        if ($has->getTableAlias() === null || $has->getTableAlias() === '') {
            $has->setTableAlias(Table::createAliasByPropertyName($name));
        }

        $getter = 'get' . ucfirst($name);
        if (!$ownerClass->hasMethod($getter) || !$ownerClass->getMethod($getter)->isPublic()) {
            throw new LogicException(sprintf(
                'Relationship "%s::$%s" requires public method %s().',
                $ownerClass->getName(),
                $name,
                $getter,
            ));
        }

        $has->setGetterMethod($getter);
    }

    /** @param ReflectionClass<Model> $ownerClass */
    private function configureCollectionSetter(ReflectionClass $ownerClass, Has $has, string $propertyName): void
    {
        if ($has->getSetterMethod() !== null) {
            $method = $has->getSetterMethod();
            if (!$ownerClass->hasMethod($method) || !$ownerClass->getMethod($method)->isPublic()) {
                throw new LogicException(sprintf(
                    'Relationship "%s::$%s" requires public method %s().',
                    $ownerClass->getName(),
                    $propertyName,
                    $method,
                ));
            }
            return;
        }

        $setMethod = 'set' . ucfirst($propertyName);
        if ($ownerClass->hasMethod($setMethod) && $ownerClass->getMethod($setMethod)->isPublic()) {
            $has->setSetterMethod($setMethod);
            return;
        }

        $singular = $this->singularize($propertyName);
        $addMethod = 'add' . ucfirst($singular);
        if ($ownerClass->hasMethod($addMethod) && $ownerClass->getMethod($addMethod)->isPublic()) {
            $has->setSetterMethod($addMethod);
            return;
        }

        throw new LogicException(sprintf(
            'Relationship "%s::$%s" requires a setter. Add either "%s()" or "%s()", or pass setterMethod in the attribute.',
            $ownerClass->getName(),
            $propertyName,
            $setMethod,
            $addMethod,
        ));
    }

    private function singularize(string $name): string
    {
        if (str_ends_with($name, 'ies') && strlen($name) > 3) {
            return substr($name, 0, -3) . 'y';
        }

        if (str_ends_with($name, 'sses') && strlen($name) > 4) {
            return substr($name, 0, -2);
        }

        if (str_ends_with($name, 's') && !str_ends_with($name, 'ss') && strlen($name) > 1) {
            return substr($name, 0, -1);
        }

        return $name;
    }

    private function camelCaseToDbName(string $input): string
    {
        return strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $input));
    }
}

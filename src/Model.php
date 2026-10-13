<?php

declare(strict_types=1);


namespace WilyCode\Database\Model;

use WilyCode\Database\Model\Attribute\Has;
use WilyCode\Database\Model\Attribute\HasMany;
use WilyCode\Database\Model\Attribute\HasManyToMany;
use WilyCode\Database\Model\Attribute\HasOne;

abstract class Model
{
    /** @var array<string, mixed> */
    private array $extraData = [];

    private bool $persisted = false;

    /**
     * Snapshot of DB-array (withDbNames=true) taken after hydration/syncOriginal().
     * @var array<string, mixed>|null
     */
    private ?array $originalDbData = null;
    /**
     * Relationship load states.
     * @var array<string, RelationState>
     */
    private array $relationStates = [];

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        ModelMetadata::for(static::class);
        $this->setProperties($data, true);
    }

    public function metadata(): ModelMetadata
    {
        return ModelMetadata::for(static::class);
    }

    /** @param array<string, mixed> $data */
    public function setProperties(array $data, bool $skipNull = false): void
    {
        $attrs = $this->metadata();

        foreach ($data as $name => $value) {
            // Allow passing DB column names (snake_case) as input keys.
            // Example: ['category_id' => 3] should map onto relationship/property "category".
            if (!$attrs->hasRelationship($name) && !$attrs->hasProperty($name)) {
                $mapped = $attrs->mapDbNameToPropertyName($name);
                if ($mapped !== null) {
                    $name = $mapped;
                }
            }

            if ($attrs->hasRelationship($name)) {
                $this->setRelationship($name, $value, $skipNull);
                continue;
            }

            // Properties may be scalars OR arrays (e.g. JSON columns decoded to array)
            if ($attrs->hasProperty($name)) {
                $this->setProperty($name, $value);
                continue;
            }

            $this->setExtraData($name, $value);
        }
    }

    public function setProperty(string $name, mixed $value): void
    {
        $property = $this->metadata()->getProperty($name);
        $setterMethod = $property->getSetterMethod();

        if ($setterMethod === null) {
            throw new \RuntimeException(sprintf('Property "%s" has no setterMethod configured.', $name));
        }

        if ($value === null) {
            $this->$setterMethod(null);
            return;
        }

        if ($property->isEnumClass()) {
            $enumClass = $property->getEnumClass();
            if ($enumClass === null) {
                throw new \LogicException(sprintf('Enum class is not configured for property "%s".', $name));
            }
            $value = $value instanceof $enumClass ? $value : $enumClass::from($value);
        } else {
            $value = ValueCaster::castToType($value, $property->getPhpType());
        }

        $this->$setterMethod($value);
    }

    public function setRelationship(string $name, mixed $value, bool $skipNull = false): void
    {
        $relationship = $this->metadata()->getRelationship($name);
        $setterMethod = $relationship->getSetterMethod();

        if ($setterMethod === null) {
            throw new \RuntimeException(sprintf('Relationship "%s" has no setterMethod configured.', $name));
        }

        if ($relationship instanceof HasOne) {
            // MariaDB 10.4 (and some custom fetchers) can produce nested relations as JSON strings.
            // Normalize early so HasOne can hydrate instead of creating a FK reference stub.
            $value = ModelHydrator::normalizeJsonLikeValue($value);

            if ($value === null) {
                $this->$setterMethod(null);
                $this->setRelationState($name, RelationState::Loaded);
                return;
            }

            if ($value instanceof Model) {
                $modelName = $relationship->getModelName();
                if ($modelName === null || !$value instanceof $modelName) {
                    throw new \InvalidArgumentException(sprintf(
                        'Relationship "%s" expects an instance of %s.',
                        $name,
                        $modelName ?? Model::class,
                    ));
                }

                $this->$setterMethod($value);
                $this->setRelationState($name, RelationState::Loaded);
                return;
            }

            if (!is_array($value)) {
                // FK scalar: create a reference object (stub) when this relationship is also a ForeignKey.
                if (!$this->metadata()->hasForeignKey($name)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Relationship "%s" expects an object payload or null.',
                        $name,
                    ));
                }

                $subModel = $relationship->newModelInstance();
                $pkProp = $subModel->metadata()->getPrimaryKeyPropertyName();
                $subModel->setProperty($pkProp, $value);
                $subModel->markHydrated();

                $this->$setterMethod($subModel);
                $this->setRelationState($name, RelationState::Reference);
                return;
            }

            $subModel = $relationship->newModelInstance(
                $this->normalizeRelatedModelData($relationship, $value),
            );

            if ($skipNull && $subModel->getPrimaryKeyValue() === null) {
                $this->$setterMethod(null);
                $this->setRelationState($name, RelationState::Loaded);
                return;
            }

            $this->$setterMethod($subModel);
            $this->setRelationState($name, RelationState::Loaded);
            return;
        }

        // HasMany / HasManyToMany. Accept already-decoded arrays or JSON strings.
        $value = ModelHydrator::normalizeJsonLikeValue($value);

        if ($value === null) {
            // Ensure collections are never null (prefer a bulk setter if available).
            if (str_starts_with($setterMethod, 'set')) {
                $this->$setterMethod([]);
                $this->setRelationState($name, RelationState::Loaded);
            } else {
                $this->setRelationState($name, RelationState::Loaded);
            }

            return;
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Relationship "%s" expects a list payload or null.',
                $name,
            ));
        }

        $items = [];
        foreach ($value as $index => $data) {
            $modelName = $relationship->getModelName();
            if ($data instanceof Model) {
                if ($modelName === null || !$data instanceof $modelName) {
                    throw new \InvalidArgumentException(sprintf(
                        'Relationship "%s" contains an invalid model at index %s.',
                        $name,
                        (string) $index,
                    ));
                }

                $subModel = $data;
            } else {
                $data = ModelHydrator::normalizeJsonLikeValue($data);

                if (is_scalar($data)) {
                    $subModel = $relationship->newModelInstance();
                    $pkProperty = $subModel->metadata()->getPrimaryKeyPropertyName();
                    $subModel->setProperty($pkProperty, $data);
                    $subModel->markHydrated();
                } elseif (is_array($data)) {
                    $subModel = $relationship->newModelInstance(
                        $this->normalizeRelatedModelData($relationship, $data),
                    );
                } else {
                    throw new \InvalidArgumentException(sprintf(
                        'Relationship "%s" contains an invalid item at index %s.',
                        $name,
                        (string) $index,
                    ));
                }
            }

            if ($skipNull && $subModel->getPrimaryKeyValue() === null) {
                continue;
            }

            $items[] = $subModel;

            // If we are using an "adder" (addTag), push items one-by-one.
            if (!str_starts_with($setterMethod, 'set')) {
                $this->$setterMethod($subModel);
            }
        }

        if (str_starts_with($setterMethod, 'set')) {
            $this->$setterMethod($items);
        }

        $this->setRelationState($name, RelationState::Loaded);
    }


    /**
     * Normalize nested relation payloads before constructing the related model.
     *
     * Database JSON objects can contain both the scalar foreign-key column
     * (for example `language_id`) and the fully eager-loaded relation
     * (`language`). JSON object key order is not guaranteed by database
     * engines, so the full relation must win regardless of the returned order.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeRelatedModelData(Has $relationship, array $data): array
    {
        $modelName = $relationship->getModelName();
        if ($modelName === null) {
            throw new \RuntimeException('Relationship modelName is not configured.');
        }

        return ModelHydrator::normalizeRow(
            ModelMetadata::for($modelName),
            $data,
            decodeJson: true,
            mapDbNames: true,
            createReferenceObjects: true,
        );
    }

    public function setExtraData(string $name, mixed $value): void
    {
        $this->extraData[$name] = $value;
    }

    public function getExtraData(?string $name = null): mixed
    {
        if ($name === null) {
            return $this->extraData;
        }

        return $this->extraData[$name] ?? null;
    }


    // Persistence / change tracking
    public function markPersisted(bool $persisted = true): void
    {
        $this->persisted = $persisted;
    }

    /**
     * Convenience helper for objects coming from hydration (or for building reference stubs).
     * Marks the model as persisted and captures the current DB snapshot as the original state.
     */
    public function markHydrated(bool $withExtraData = false): void
    {
        $this->markPersisted(true);
        $this->syncOriginal(withExtraData: $withExtraData);
    }

    public function isPersisted(): bool
    {
        return $this->persisted;
    }

    public function isNew(): bool
    {
        return !$this->persisted || $this->getPrimaryKeyValue() === null;
    }

    /**
     * Capture the current DB-array snapshot (withDbNames=true) as the "original" state.
     * Call this right after hydration (or after a successful INSERT/UPDATE).
     */
    public function syncOriginal(bool $withExtraData = false): void
    {
        $this->originalDbData = $this->asArray(withDbNames: true, withExtraData: $withExtraData);
    }

    /**
     * Returns DB-named values that differ from the original snapshot.
     *
     * @return array<string, mixed>
     */
    public function getDirtyDbValues(bool $withExtraData = false): array
    {
        $current = $this->asArray(withDbNames: true, withExtraData: $withExtraData);
        $original = $this->originalDbData ?? [];

        $dirty = [];
        foreach ($current as $k => $v) {
            if (!array_key_exists($k, $original) || !$this->valuesEqual($original[$k], $v)) {
                $dirty[$k] = $v;
            }
        }

        return $dirty;
    }

    /**
     * DB array suitable for INSERT (primary key is removed when null).
     *
     * @return array<string, mixed>
     */
    public function getInsertDbValues(bool $withExtraData = false): array
    {
        $data = $this->asArray(withDbNames: true, withExtraData: $withExtraData);

        $pkDb = $this->metadata()->getPrimaryKeyDbName();
        if ($this->getPrimaryKeyValue() === null) {
            unset($data[$pkDb]);
        }

        return $data;
    }

    /**
     * DB array suitable for UPDATE (primary key is removed).
     *
     * @return array<string, mixed>
     */
    public function getUpdateDbValues(bool $onlyDirty = true, bool $withExtraData = false): array
    {
        $data = $onlyDirty
            ? $this->getDirtyDbValues(withExtraData: $withExtraData)
            : $this->asArray(withDbNames: true, withExtraData: $withExtraData);

        $pkDb = $this->metadata()->getPrimaryKeyDbName();
        unset($data[$pkDb]);

        return $data;
    }

    /**
     * Helps a repository decide which persistence action to take.
     * - insert: model is new (not persisted yet OR primary key is null)
     * - update: model is persisted and has dirty fields
     * - none:   model is persisted and unchanged
     */
    public function getPersistenceAction(): PersistenceAction
    {
        if ($this->isNew()) {
            return PersistenceAction::Insert;
        }

        return $this->getDirtyDbValues() !== [] ? PersistenceAction::Update : PersistenceAction::None;
    }

    private function valuesEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) || is_array($b)) {
            return json_encode($a, JSON_THROW_ON_ERROR) === json_encode($b, JSON_THROW_ON_ERROR);
        }

        return $a === $b;
    }

    // Relationship states

    public function getRelationState(string $name): RelationState
    {
        return $this->relationStates[$name] ?? RelationState::Unloaded;
    }

    public function isRelationLoaded(string $name): bool
    {
        return $this->getRelationState($name) === RelationState::Loaded;
    }

    public function isRelationReference(string $name): bool
    {
        return $this->getRelationState($name) === RelationState::Reference;
    }

    protected function setRelationState(string $name, RelationState $state): void
    {
        $this->relationStates[$name] = $state;
    }

    /**
     * @param list<string> $skipNames
     * @return array<string, mixed>
     */
    public function asArray(bool $withDbNames = false, bool $withExtraData = false, array $skipNames = []): array
    {
        $converter = new ModelArrayConverter($this);

        $array = $converter->asArray($withDbNames, $withExtraData, $skipNames);

        foreach ($skipNames as $skipName) {
            if (isset($array[$skipName])) {
                unset($array[$skipName]);
            }
        }

        return $array;
    }

    // Primary key helpers

    /**
     * Returns the *property* name of the primary key.
     */
    public function getPrimaryKeyPropertyName(): string
    {
        return $this->metadata()->getPrimaryKeyPropertyName();
    }

    /**
     * Returns the current primary key value (or null when not set).
     */
    public function getPrimaryKeyValue(): mixed
    {
        $getter = $this->metadata()->getPrimaryKeyGetterMethod();

        return $this->$getter();
    }

    /**
     * Sets the primary key value using the PK setter (if present).
     */
    public function setPrimaryKeyValue(mixed $value): void
    {
        $pkProp = $this->metadata()->getPrimaryKeyPropertyName();
        $this->setProperty($pkProp, $value);
    }


}
<?php

declare(strict_types=1);


namespace WilyCode\Database\Model;

use BackedEnum;
use DateTimeInterface;
use stdClass;

/**
 * Converts a hydrated Model (including loaded relationships) into an array.
 *
 * Key points:
 * - when $withDbNames=true, keys are DB column names (Property::dbName)
 * - for properties that are BOTH ForeignKey + Relationship (e.g. #[ForeignKey] #[HasOne] User $user),
 *   we output the FK scalar value (user_id => 123) instead of the related Model object
 * - when $withDbNames=false, relationships are recursively converted to arrays
 * - JSON columns decoded to PHP arrays remain arrays (also supports stdClass just in case)
 */
class ModelArrayConverter
{
    private Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * @param bool  $withDbNames     When true, output keys as DB column names (good for persistence)
     * @param bool  $withExtraData   When true, merge extraData into the result
     * @param list<string> $skipNames Supports dot-notation for nested relations, e.g. ['user.password', 'tags.pivot']
     * @return array<string, mixed>
     */
    public function asArray(bool $withDbNames = false, bool $withExtraData = false, array $skipNames = []): array
    {
        $data = [];

        $attributes = $this->model->metadata();

        // 1) scalar properties (and FK columns for relationship-properties marked as ForeignKey)
        foreach ($attributes->getProperties() as $name => $property) {
            if ($this->isSkipped($name, $skipNames)) {
                continue;
            }

            $key = $withDbNames ? $property->getDbName() : $name;

            $getterMethod = $property->getGetterMethod();
            if ($getterMethod === null) {
                // no getter – skip (or you can choose to read property via reflection)
                continue;
            }

            $value = $this->model->$getterMethod();

            // If a property is both ForeignKey + Relationship AND we are building DB array,
            // output the FK scalar(s), not the related object(s).
            if ($withDbNames && $attributes->hasForeignKey($name) && $attributes->hasRelationship($name)) {
                $value = $this->extractForeignKeyValue($value);
            } else {
                $nestedSkip = $this->getNestedSkipNames($name, $skipNames);
                $value = $this->normalizeValue($value, $withDbNames, $withExtraData, $nestedSkip);
            }

            $data[$key] = $value;
        }

        // For DB arrays we intentionally stop here (no nested relations).
        if ($withDbNames) {
            return $withExtraData ? array_merge($data, $this->model->getExtraData()) : $data;
        }

        // 2) relationships (only those not already present in $data)
        foreach ($attributes->getRelationships() as $name => $relation) {
            if (array_key_exists($name, $data)) {
                continue;
            }

            if ($this->isSkipped($name, $skipNames)) {
                continue;
            }

            $getterMethod = $relation->getGetterMethod();
            if ($getterMethod === null) {
                continue;
            }

            $value = $this->model->$getterMethod();
            $nestedSkip = $this->getNestedSkipNames($name, $skipNames);

            $data[$name] = $this->normalizeValue($value, false, $withExtraData, $nestedSkip);
        }

        if ($withExtraData) {
            return array_merge($data, $this->model->getExtraData());
        }

        return $data;
    }

    /**
     * Normalize arbitrary values for array output.
     * - Model => recursively asArray()
     * - array of Models => map recursively
     * - BackedEnum => ->value
     * - DateTimeInterface => ATOM string
     * - stdClass => convert to associative array recursively
     *
     * @param list<string> $nestedSkipNames
     * @throws \JsonException
     */
    private function normalizeValue(mixed $value, bool $withDbNames, bool $withExtraData, array $nestedSkipNames): mixed
    {
        if ($value instanceof Model) {
            return $value->asArray($withDbNames, $withExtraData, $nestedSkipNames);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof stdClass) {
            /** @var array<array-key, mixed> $arr */
            $arr = json_decode(json_encode($value, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            return $this->normalizeValue($arr, $withDbNames, $withExtraData, $nestedSkipNames);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->normalizeValue($v, $withDbNames, $withExtraData, $nestedSkipNames);
            }
            return $out;
        }

        return $value;
    }

    /**
     * Extract FK scalar(s) from a relationship-property marked as ForeignKey.
     * Supports:
     * - Model => model PK
     * - array<Model> => array of PKs
     * - scalar => scalar
     */
    private function extractForeignKeyValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Model) {
            return $value->getPrimaryKeyValue();
        }

        if (is_array($value)) {
            $ids = [];
            foreach ($value as $item) {
                if ($item instanceof Model) {
                    $ids[] = $item->getPrimaryKeyValue();
                    continue;
                }
                $ids[] = $item;
            }
            return $ids;
        }

        // scalar / other
        return $value;
    }

    /** @param list<string> $skipNames */
    private function isSkipped(string $key, array $skipNames): bool
    {
        return in_array($key, $skipNames, true);
    }

    /**
     * @param list<string> $skipNames
     * @return list<string>
     */
    private function getNestedSkipNames(string $key, array $skipNames): array
    {
        $nestedSkipNames = [];

        foreach ($skipNames as $skipName) {
            if (!str_contains($skipName, '.')) {
                continue;
            }

            $parts = explode('.', $skipName);
            if ($parts[0] === $key) {
                $nestedSkipNames[] = implode('.', array_slice($parts, 1));
            }
        }

        return $nestedSkipNames;
    }
}

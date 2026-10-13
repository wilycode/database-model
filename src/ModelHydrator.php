<?php

declare(strict_types=1);


namespace WilyCode\Database\Model;

/**
 * Small helper that:
 * - maps DB column names to model property names (dbName -> propertyName),
 * - JSON-decodes relation envelopes and mapped array properties, preserving scalar text,
 * - avoids mapping FK scalar columns (e.g. category_id) onto relationship properties (e.g. category),
 *   because ForeignKey properties are intended for persistence (FK from relationship PK), not for hydration.
 */
final class ModelHydrator
{
    /** Decode one JSON envelope; nested fields are interpreted using their own model metadata. */
    public static function normalizeJsonLikeValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        $decoded = self::tryDecodeJson($value);
        return $decoded === self::NO_JSON ? $value : $decoded;
    }

    /**
     * @template T of Model
     * @param class-string<T> $modelClass
     * @param array<string, mixed> $row
     * @return T
     */
    public static function hydrate(string $modelClass, array $row, bool $decodeJson = true, bool $mapDbNames = true, bool $createReferenceObjects = true): Model
    {
        $attributes = ModelMetadata::for($modelClass);
        try {
            $data = self::normalizeRow($attributes, $row, $decodeJson, $mapDbNames, $createReferenceObjects);
        } catch (\UnexpectedValueException $error) {
            throw new \UnexpectedValueException($modelClass . ': ' . $error->getMessage(), 0, $error);
        }

        $model = new $modelClass($data);

        self::markPersistedRecursive($model);

        return $model;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(ModelMetadata $attributes, array $row, bool $decodeJson = true, bool $mapDbNames = true, bool $createReferenceObjects = true): array
    {
        $data = [];

        foreach ($row as $key => $value) {
            $name = $key;

            if ($mapDbNames) {
                $mapped = $attributes->mapDbNameToPropertyName($key);

                if ($mapped !== null) {
                    // For properties that are BOTH Relationship + ForeignKey (e.g. category_id -> category),
                    // we can map the FK scalar onto the relationship name so Model::setRelationship()
                    // can create a reference object (stub).
                    if ($attributes->hasRelationship($mapped) && $attributes->hasForeignKey($mapped)) {
                        if ($createReferenceObjects) {
                            $name = $mapped;
                        }
                    } else {
                        $name = $mapped;
                    }
                }
            }

            $type = $attributes->hasProperty($name) ? $attributes->getProperty($name)->getPhpType() : null;
            if ($decodeJson && $type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                if (is_string($value)) {
                    try {
                        $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\JsonException $error) {
                        throw new \UnexpectedValueException(sprintf(
                            'Invalid JSON for %s.%s: %s', $attributes->getTableName(), $name, $error->getMessage(),
                        ), 0, $error);
                    }
                }
                if (!is_array($value) && !($value === null && $type->allowsNull())) {
                    throw new \UnexpectedValueException(sprintf(
                        'Expected an array%s for %s.%s.', $type->allowsNull() ? ' or null' : '',
                        $attributes->getTableName(), $name,
                    ));
                }
            } elseif ($decodeJson && $attributes->hasRelationship($name)) {
                $value = self::normalizeJsonLikeValue($value);
            }

            if ($createReferenceObjects && $mapDbNames) {
                // If we already captured a full object/array for this relationship, don't overwrite it with a scalar FK.
                $existingIsFullRelation = array_key_exists($name, $data)
                    && (is_array($data[$name]) || $data[$name] instanceof Model);
                $incomingIsScalarReference = !is_array($value) && !$value instanceof Model;

                if ($existingIsFullRelation && $incomingIsScalarReference) {
                    continue;
                }
            }

            $data[$name] = $value;
        }

        return $data;
    }

    private static function markPersistedRecursive(Model $model): void
    {
        $seen = [];
        $stack = [$model];

        while (count($stack) > 0) {
            /** @var Model $m */
            $m = array_pop($stack);

            $oid = spl_object_id($m);
            if (isset($seen[$oid])) {
                continue;
            }
            $seen[$oid] = true;

            $m->markHydrated();

            $attrs = $m->metadata();
            foreach ($attrs->getRelationships() as $relName => $rel) {
                $getter = $rel->getGetterMethod();
                if ($getter === null) {
                    continue;
                }

                $val = $m->$getter();

                if ($val instanceof Model) {
                    $stack[] = $val;
                    continue;
                }

                if (is_array($val)) {
                    foreach ($val as $item) {
                        if ($item instanceof Model) {
                            $stack[] = $item;
                        }
                    }
                }
            }
        }
    }

    private const string NO_JSON = '__NO_JSON__';

    private static function tryDecodeJson(string $value): mixed
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return self::NO_JSON;
        }

        // JSON null
        if ($trimmed === 'null') {
            return null;
        }

        $first = $trimmed[0];
        $last  = $trimmed[strlen($trimmed) - 1];

        $looksLikeJsonObject = $first === '{' && $last === '}';
        $looksLikeJsonArray  = $first === '[' && $last === ']';

        if (!$looksLikeJsonObject && !$looksLikeJsonArray) {
            return self::NO_JSON;
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return self::NO_JSON;
        }

        return $decoded;
    }
}

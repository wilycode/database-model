<?php

declare(strict_types=1);


namespace WilyCode\Database\Model;

final class ValueCaster
{
    private function __construct() {}

    public static function castToType(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($type === null) return $value;
        if ($value === null) return null;

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                if ($t instanceof \ReflectionNamedType && $t->getName() !== 'null') {
                    return self::castToType($value, $t);
                }
            }
            return $value;
        }

        if (!($type instanceof \ReflectionNamedType)) return $value;

        if ($value === '' && $type->allowsNull()) return null;

        return match ($type->getName()) {
            'int'    => (is_int($value) ? $value : (is_numeric($value) ? (int)$value : $value)),
            'float'  => (is_float($value) ? $value : (is_numeric($value) ? (float)$value : $value)),
            'bool'   => self::castToBool($value),
            'string' => (is_string($value) ? $value : (is_scalar($value) ? (string)$value : $value)),
            default  => $value,
        };
    }

    private static function castToBool(mixed $value): mixed
    {
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value !== 0;

        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1','true','yes','on'], true)) return true;
            if (in_array($v, ['0','false','no','off'], true)) return false;
            if (is_numeric($v)) return ((int)$v) !== 0;
        }

        return $value;
    }
}

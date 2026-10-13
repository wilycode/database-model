<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    private ?string $name = null;
    private ?string $alias = null;

    public function __construct(?string $name = null, ?string $alias = null)
    {
        $this->name = $name;
        $this->alias = $alias;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setAlias(?string $alias): void
    {
        $this->alias = $alias;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }


    /**
     * Builds a short alias from a path like "category.tags".
     * Each segment uses createAliasByPropertyName().
     */
    public static function createAliasByPath(string $path): string
    {
        $segments = preg_split('/\./', $path, -1, PREG_SPLIT_NO_EMPTY);
        if ($segments === false) {
            return '';
        }

        $alias = '';
        foreach ($segments as $segment) {
            $alias .= self::createAliasByPropertyName($segment);
        }

        return $alias;
    }

    /**
     * Default alias for relationship/table usage derived from a camelCase property name.
     *
     * Examples:
     * - tags => t
     * - tagThing => th (picks first available letter per word to avoid duplicates)
     */
    public static function createAliasByPropertyName(string $propertyName): string
    {
        $propertyName = trim($propertyName);
        if ($propertyName === '') {
            return '';
        }

        // Split by underscores first, then camelCase.
        $parts = preg_split('/_+/', $propertyName, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            $parts = [$propertyName];
        }

        $lettersUsed = [];
        $alias = '';

        foreach ($parts as $part) {
            $words = preg_split('/(?=[A-Z])/', $part, -1, PREG_SPLIT_NO_EMPTY);
            if ($words === false) {
                $words = [$part];
            }

            foreach ($words as $word) {
                $w = strtolower($word);
                $chosen = $w[0];
                // If first letter was already used, try the next letters in the word.
                if (isset($lettersUsed[$chosen])) {
                    $len = strlen($w);
                    for ($i = 1; $i < $len; $i++) {
                        $cand = $w[$i];
                        if (!isset($lettersUsed[$cand]) && ctype_alpha($cand)) {
                            $chosen = $cand;
                            break;
                        }
                    }
                }

                $alias .= $chosen;
                $lettersUsed[$chosen] = true;
            }
        }

        return strtolower($alias);
    }

    public static function createAliasByModelName(string $modelName): string
    {
        if (($pos = strrpos($modelName, '\\')) !== false) {
            $modelName = substr($modelName, $pos + 1);
        }

        preg_match_all('/[A-Z]+(?![a-z])|[A-Z][a-z]*/', $modelName, $m);
        $words = $m[0];

        $alias = '';
        foreach ($words as $w) {
            $alias .= strtolower($w[0]);
        }

        return $alias;
    }
}
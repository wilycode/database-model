# WilyCode Database Model

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/database-model/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/database-model/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/wilycode/database-model)](https://packagist.org/packages/wilycode/database-model)

**Attribute-based PHP models with validated metadata, JSON-aware hydration, relationships, and change tracking.** Define a model once, convert database rows into objects, and obtain database-ready values for persistence. PHP 8.4+.

> **WilyCode PHP Ecosystem**
>
> Part of **[WilyCode](https://github.com/wilycode/wilycode)** — a collection of independent, reusable PHP components. Each package has its own repository, releases, and Composer installation.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

## Table of contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Model attributes](#model-attributes)
  - [Table and repository](#table-and-repository)
  - [Primary key, property, and foreign key](#primary-key-property-and-foreign-key)
  - [Relationship attributes](#relationship-attributes)
- [Model metadata](#model-metadata)
- [Creating and updating models](#creating-and-updating-models)
- [Hydrating database rows](#hydrating-database-rows)
  - [Column names and type conversion](#column-names-and-type-conversion)
  - [Foreign-key references](#foreign-key-references)
  - [Fully loaded HasOne relationships](#fully-loaded-hasone-relationships)
  - [HasMany and HasManyToMany collections](#hasmany-and-hasmanytomany-collections)
  - [JSON array properties and nested JSON](#json-array-properties-and-nested-json)
- [Relationship states](#relationship-states)
- [Converting models to arrays](#converting-models-to-arrays)
- [Change tracking and persistence](#change-tracking-and-persistence)
- [Enums and value conversion](#enums-and-value-conversion)
- [Validation and error handling](#validation-and-error-handling)
- [API reference](#api-reference)
- [Integration with the database ecosystem](#integration-with-the-database-ecosystem)
- [Security and limitations](#security-and-limitations)
- [Related packages](#related-packages)
- [Development](#development)
- [Contributing and license](#contributing-and-license)

## Overview

`database-model` is **not an ORM or a database connection library**. It provides the object-mapping layer used by applications and repositories:

```text
Database result row (array)
           |
           | ModelHydrator::hydrate()
           v
      Model instance
           |
           +-- mapped properties and backed enums
           +-- HasOne / HasMany / HasManyToMany relations
           +-- relationship load states
           +-- persisted state and original-value snapshot
           |
           | getInsertDbValues() / getUpdateDbValues()
           v
Database-ready values (array) -> your repository / query executor
```

The package does **not** execute SQL, save models automatically, lazy-load relationships, generate migrations, or perform JOINs. Use a repository or another database component for those operations.

## Requirements

- PHP **8.4 or newer**.
- PHP extensions **`ctype`** and **`json`**.
- No third-party runtime Composer dependencies.

## Installation

```bash
composer require wilycode/database-model
```

## Quick start

Define a related model and a model containing its foreign key. Every mapped property needs a PHP type and public getter/setter methods.

```php
<?php

declare(strict_types=1);

use WilyCode\Database\Model\Attribute\ForeignKey;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\PrimaryKey;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Attribute\Table;
use WilyCode\Database\Model\Model;

#[Table('users', 'u')]
final class User extends Model
{
    #[PrimaryKey]
    private ?int $id = null;

    #[Property('display_name')]
    private ?string $displayName = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getDisplayName(): ?string { return $this->displayName; }
    public function setDisplayName(?string $name): void { $this->displayName = $name; }
}

#[Table('posts', 'p')]
final class Post extends Model
{
    #[PrimaryKey]
    private ?int $id = null;

    #[Property]
    private ?string $title = null;

    #[ForeignKey('user_id')]
    #[HasOne]
    private ?User $user = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): void { $this->title = $title; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): void { $this->user = $user; }
}
```

Hydrate a row returned by a database driver:

```php
use WilyCode\Database\Model\ModelHydrator;

$post = ModelHydrator::hydrate(Post::class, [
    'id' => 10,
    'title' => 'Hello',
    'user_id' => 3,
]);

$post->getId();                   // 10
$post->getUser()?->getId();        // 3 (ID-only reference)
$post->isRelationReference('user'); // true
$post->isPersisted();             // true
```

The `user` reference contains **only the ID**. It does not automatically load the user's other fields from the database.

## Model attributes

### Table and repository

Declare `#[Table]` on each model. Supply a table name and optional alias, or allow the library to derive them from the model class name:

```php
#[Table('blog_posts', 'p')]
final class BlogPost extends Model { /* mapped properties and accessors */ }
```

`#[Repository(YourRepository::class)]` optionally associates a repository class name with a model. It is **metadata only**: no repository object is created here. Without this attribute, `getRepositoryName()` throws.

Default table aliases are derived from the model name. For relation attributes, default aliases are derived from property names. **These are SQL table aliases, not prefixes for output columns or automatic table-name prefixes.**

### Primary key, property, and foreign key

| Attribute | Purpose | Example |
| --- | --- | --- |
| `#[PrimaryKey]` | Identifies the single primary key property. | `#[PrimaryKey] private ?int $id = null;` |
| `#[Property]` | Maps a scalar, enum, or array property. | `#[Property] private ?string $title = null;` |
| `#[Property('created_at')]` | Overrides the column name. | `created_at` |
| `#[ForeignKey('user_id')]` | Marks a mapped foreign-key column; may be combined with `#[HasOne]`. | `user_id` |

Unspecified property column names are converted from `camelCase` to `snake_case`. Foreign-key properties get an `_id` suffix by default when their derived name does not already end in `_id`.

Exactly **one** of `PrimaryKey`, `Property`, or `ForeignKey` may be applied to the same property. A foreign-key mapping **may** also have one relationship attribute, as in `#[ForeignKey] #[HasOne]`.

### Relationship attributes

| Attribute | Relation | Notes |
| --- | --- | --- |
| `#[HasOne]` | One related model | Target class is inferred from the typed property. |
| `#[HasMany(modelName: Comment::class)]` | Collection of models | Explicit model class; use a public bulk setter or adder. |
| `#[HasManyToMany(modelName: Tag::class)]` | Many-to-many collection | Optional linking-table/column metadata. |

For collection relationships, the metadata layer looks for a public `setTags(array $tags)` or an appropriate `addTag(Tag $tag)` (or an explicitly named setter). It validates the relationship's target model type. For a `HasManyToMany`, `linkingTable`, `linkingColumn`, and `foreignKey` can be configured as attribute arguments; defaults are derived from table names. This library does **not** itself query or write the linking table.

The relationship attribute also accepts optional `onCondition`, `setterMethod`, `getterMethod`, `foreignKey`, and `tableAlias` metadata. `onCondition` is a stored description of the JOIN condition for other components; the model layer does not execute it.

## Model metadata

`ModelMetadata::for()` validates a model once and caches its metadata:

```php
use WilyCode\Database\Model\ModelMetadata;

$metadata = ModelMetadata::for(Post::class);

$metadata->getTableName();            // 'posts'
$metadata->getTableAlias();           // 'p'
$metadata->getPrimaryKeyDbName();     // 'id'
$metadata->getDbPropertyName('title'); // 'title'
$metadata->getForeignKeyDbName('user'); // 'user_id'
$metadata->getScalarDbProperties();   // ['id' => 'id', 'title' => 'title', 'user' => 'user_id']
$metadata->hasRelationship('user');   // true
$metadata->mapDbNameToPropertyName('user_id'); // 'user'
```

The facade exposes property mappings, foreign keys, relationship definitions, and the optional repository class. Reflection and validation live in an internal metadata factory. Call `ModelMetadata::clearCache()` only when you intentionally need to rebuild metadata (for example, in tests).

## Creating and updating models

Models accept mapped PHP property names or mapped database column names:

```php
$post = new Post([
    'title' => 'Draft',
    'user_id' => 3,
]);

$post->setProperties(['title' => 'Published']);
$post->setProperty('title', 'Edited');
$post->setPrimaryKeyValue(10);
```

Unrecognized keys are retained in `extraData` rather than becoming mapped database columns:

```php
$post->setExtraData('score', 0.98);
$post->getExtraData('score'); // 0.98
$post->getExtraData();        // ['score' => 0.98]
```

`extraData` is excluded from persistence arrays and change tracking **by default**. Enable it only when you explicitly call a method's `withExtraData: true` option and trust those keys.

## Hydrating database rows

### Column names and type conversion

`ModelHydrator::hydrate(ModelClass::class, $row)` converts a database row to a typed model, marks it as persisted, and captures an initial snapshot. It also recursively marks hydrated related objects as persisted.

```php
$post = ModelHydrator::hydrate(Post::class, [
    'id' => '10',
    'title' => 'Hello',
    'user_id' => '3',
]);

$post->getId();            // int(10)
$post->getUser()?->getId(); // int(3)
```

Built-in scalar conversion handles common integer, float, boolean, and string inputs. Backed enum values are converted to enum cases. Invalid types can produce PHP type errors or enum `ValueError`; the library does not perform schema-level validation.

### Foreign-key references

A property marked with both `#[ForeignKey]` and `#[HasOne]` can be hydrated from its scalar foreign-key column **without a JOIN**:

```php
$post = ModelHydrator::hydrate(Post::class, ['id' => 10, 'user_id' => 3]);

$post->getUser()?->getId();        // 3
$post->getUser()?->getDisplayName(); // null: not loaded
$post->isRelationReference('user'); // true
```

The related object is a persisted reference, not a fully loaded relation. Use a repository or a query selecting the actual related data when you need more than its ID.

### Fully loaded HasOne relationships

A related object can be supplied as an array or as a JSON object string under the **relationship property name**:

```php
$post = ModelHydrator::hydrate(Post::class, [
    'id' => 10,
    'title' => 'Hello',
    'user_id' => 3,
    'user' => '{"id":3,"display_name":"Ada"}',
]);

$post->getUser()?->getId();          // 3
$post->getUser()?->getDisplayName(); // 'Ada'
$post->isRelationLoaded('user');     // true
```

If both the scalar FK and full relationship are returned, the full object is preserved regardless of their order in the database row. A SQL expression such as `JSON_OBJECT(...) AS user` can produce this payload; constructing that SQL belongs to your query layer.

Flat JOIN aliases like `userName` or `user.name` **do not automatically hydrate nested relations**. They remain unmapped or extra data unless the model explicitly maps those property names.

### HasMany and HasManyToMany collections

Use collection attributes with an explicit related model. For example, on a model containing `tags`:

```php
#[HasManyToMany(modelName: Tag::class, linkingTable: 'post_tags')]
private array $tags = [];

public function getTags(): array { return $this->tags; }
public function setTags(array $tags): void { $this->tags = $tags; }
```

Assign full related objects, arrays of field values, JSON arrays, or identifiers:

```php
$article->setProperties(['tags' => [1, '4']]); // Creates ID-only Tag references.
$article->setProperties(['tags' => [['id' => 1, 'name' => 'PHP']]]);
```

The same assignment mechanism handles `#[HasMany(modelName: Comment::class)]`. `null` is normalized to an empty collection for bulk-setter relationships. Assignments do **not** automatically persist or fetch linked rows.

### JSON array properties and nested JSON

A mapped PHP `array` property can be hydrated from a JSON object or array:

```php
// Given a model with:
// #[Property] private array $settings = [];
// public function getSettings(): array { return $this->settings; }
// public function setSettings(array $settings): void { $this->settings = $settings; }

$document = ModelHydrator::hydrate(Document::class, [
    'id' => 1,
    'settings' => '{"theme":"dark","flags":[true,false]}',
]);

$document->getSettings(); // ['theme' => 'dark', 'flags' => [true, false]]
```

In this example, `Document` represents your own mapped model with the specified property and accessors. See `tests/Model/JsonDocument.php` for a complete, tested implementation.

Array-typed mapped columns accept JSON objects/arrays (or already decoded PHP arrays). Invalid JSON, scalar JSON values, and JSON `null` on a non-nullable array cause `UnexpectedValueException`. A nullable array can accept `null`. An empty string is not valid JSON. Ordinary string properties preserve literal text, even when it resembles JSON.

For nested relation JSON returned as strings by some MariaDB versions, hydration normalizes each nested model boundary. You can also access `ModelHydrator::normalizeRow()` directly for advanced mapping, with optional `decodeJson`, `mapDbNames`, and `createReferenceObjects` flags.

## Relationship states

Each relation can be in one of three states:

| State | Meaning |
| --- | --- |
| `RelationState::Unloaded` | No relationship value has been assigned/hydrated. |
| `RelationState::Reference` | A related model stub exists with an ID, but its fields were not loaded. |
| `RelationState::Loaded` | Relation was explicitly populated (including an explicit `null` or empty collection). |

```php
$post->getRelationState('user');
$post->isRelationReference('user');
$post->isRelationLoaded('user');
```

These flags do not trigger lazy loading; they only describe what is currently available.

## Converting models to arrays

`asArray()` converts model properties and loaded relations to nested arrays. With database names enabled, it includes **mapped DB columns only**, translating `#[ForeignKey] #[HasOne]` to the related model's primary key:

```php
$post->asArray();
// ['id' => 10, 'title' => 'Hello', 'user' => ['id' => 3, 'displayName' => 'Ada']]

$post->asArray(withDbNames: true);
// ['id' => 10, 'title' => 'Hello', 'user_id' => 3]
```

Backed enums become their scalar values, dates implementing `DateTimeInterface` become ISO 8601 strings, and nested models are recursively converted. An unmapped relation is **not** included in a DB-named array.

Use `skipNames` for selected properties, including dot-separated nested paths:

```php
$data = $post->asArray(skipNames: ['user.displayName']);
```

Use `withExtraData: true` only when you want additional non-model fields in the resulting array. Beware of cycles in in-memory relationship graphs when converting recursively.

## Change tracking and persistence

Hydration marks a model as persisted and records its current database representation. A model built with `new` is considered new until marked persisted and assigned a primary key.

```php
$post = ModelHydrator::hydrate(Post::class, [
    'id' => 10,
    'title' => 'Old title',
    'user_id' => 3,
]);

$post->getPersistenceAction(); // PersistenceAction::None

$post->setTitle('New title');
$post->getPersistenceAction(); // PersistenceAction::Update
$post->getDirtyDbValues();     // ['title' => 'New title']
$post->getUpdateDbValues();    // ['title' => 'New title']

$post->syncOriginal();         // Call after a successful database UPDATE.
$post->getPersistenceAction(); // PersistenceAction::None
```

For an unsaved model:

```php
$newPost = new Post(['title' => 'Draft', 'user_id' => 3]);

$newPost->getPersistenceAction(); // PersistenceAction::Insert
$newPost->getInsertDbValues();    // ['title' => 'Draft', 'user_id' => 3] (no null PK)
```

| Method | Purpose |
| --- | --- |
| `markPersisted(bool $persisted = true)` | Set the in-memory persisted flag. |
| `markHydrated(bool $withExtraData = false)` | Mark persisted and capture the current DB snapshot. |
| `syncOriginal(bool $withExtraData = false)` | Replace the original-value snapshot. |
| `isPersisted()` / `isNew()` | Inspect persistence status. |
| `getDirtyDbValues()` | Return changed DB-named values. |
| `getInsertDbValues()` | Return DB-named fields, omitting a null primary key. |
| `getUpdateDbValues(bool $onlyDirty = true)` | Return update fields, always omitting the primary key. |
| `getPersistenceAction()` | Return `Insert`, `Update`, or `None`. |

**This package does not write to a database.** Your repository must perform the INSERT/UPDATE and call `syncOriginal()` or `markHydrated()` after successful persistence. `getDirtyDbValues()` compares arrays by their JSON representation; order in associative arrays can therefore matter.

## Enums and value conversion

Backed enum properties marked with `#[Property]` accept either an enum case or its backing value:

```php
enum Status: string { case Draft = 'draft'; case Published = 'published'; }

// In a mapped model:
// #[Property] private ?Status $status = null;
// public function getStatus(): ?Status { return $this->status; }
// public function setStatus(?Status $status): void { $this->status = $status; }

// Both inputs are accepted by the model constructor/setProperties:
$model->setProperties(['status' => 'draft']);
$model->setProperties(['status' => Status::Published]);
```

`ValueCaster::castToType()` handles basic scalar conversions. For booleans, it understands common string values such as `true`, `false`, `yes`, `no`, `on`, `off`, `1`, and `0`. Do not treat these conversions as a substitute for input validation.

## Validation and error handling

Metadata is intentionally strict and rejects invalid definitions early:

| Configuration problem | Typical result |
| --- | --- |
| Missing `#[Table]` or `#[PrimaryKey]` | `LogicException` |
| More than one primary key or duplicate DB column mapping | `LogicException` |
| Missing PHP type or public getter/setter on mapped property | `LogicException` |
| Conflicting mapping or relationship attributes | `LogicException` |
| Relation points to a class not extending `Model` | `LogicException` |
| Missing public collection setter/adder | `LogicException` |
| Invalid data passed into a collection relation | `InvalidArgumentException` |
| Invalid JSON for a mapped array property | `UnexpectedValueException` |
| Backed enum has an invalid backing value | `ValueError` |

```php
use WilyCode\Database\Model\ModelHydrator;

try {
    $post = ModelHydrator::hydrate(Post::class, $row);
} catch (\LogicException | \UnexpectedValueException | \InvalidArgumentException | \TypeError | \ValueError $exception) {
    // Log safely; avoid returning implementation details to end users.
    error_log($exception->getMessage());
}
```

Not every invalid input is converted to a domain-specific exception. Native PHP type errors can still occur, and a valid model does not guarantee that its values satisfy database constraints.

## API reference

| Component | Key public API |
| --- | --- |
| `Model` | `__construct`, `metadata`, `setProperties`, `setProperty`, `setRelationship`, `asArray`, `getExtraData`, `setExtraData` |
| `Model` persistence | `isNew`, `isPersisted`, `markPersisted`, `markHydrated`, `syncOriginal`, `getDirtyDbValues`, `getInsertDbValues`, `getUpdateDbValues`, `getPersistenceAction` |
| `Model` relations | `getRelationState`, `isRelationLoaded`, `isRelationReference` |
| `Model` primary key | `getPrimaryKeyValue`, `setPrimaryKeyValue`, `getPrimaryKeyPropertyName` |
| `ModelMetadata` | `for`, `clearCache`, `getTableName`, `getTableAlias`, `getProperties`, `getRelationships`, `getForeignKeys`, `getScalarDbProperties`, `getRepositoryName` |
| `ModelHydrator` | `hydrate`, `normalizeRow`, `normalizeJsonLikeValue` |
| `ModelArrayConverter` | `asArray` |
| `ValueCaster` | `castToType` |
| `PersistenceAction` | `Insert`, `Update`, `None` |
| `RelationState` | `Unloaded`, `Reference`, `Loaded` |

The relationship and mapping attributes are public configuration objects. Internal `ModelMetadataFactory` and `ModelMetadataDefinition` are implementation details; application code should normally access them through `ModelMetadata::for()`.

## Integration with the database ecosystem

This package accepts database rows as arrays and exposes database-ready arrays. You can combine it with WilyCode's separate SQL builder, query executor, and database drivers:

```php
// Assume $executor is a configured QueryExecutorInterface.
// The query builder and executor are separate Composer packages.

use WilyCode\Database\Query\Query;
use WilyCode\Database\Model\ModelHydrator;

$row = $executor->fetch(
    Query::select('posts', 'p')
        ->where('p.id', 10),
);

if ($row !== []) {
    $post = ModelHydrator::hydrate(Post::class, $row);
}
```

For eager-loaded relations, select a column under the relationship name containing a JSON object or array. `database-model` does **not** turn flat prefixed JOIN aliases into nested model objects. Queries, fetching, and persistence remain the responsibility of your repository and database adapter.

## Security and limitations

- Treat identifiers and raw SQL as untrusted until validated by your query layer. Model metadata does not sanitize SQL.
- Avoid exposing `extraData` or nested relations containing sensitive fields without filtering (for example, `skipNames: ['user.password']`).
- Hydration does not lazily load missing relations or perform authorization checks.
- Array columns are decoded for models but writing JSON values to a specific driver/column format remains a repository responsibility.
- Foreign-key reference objects contain only IDs; do not treat them as fully loaded or verified database records.
- These models are not a persistence unit of work or a drop-in replacement for an ORM.

## Related packages

| Package | Purpose |
| --- | --- |
| [wilycode/database-query](https://github.com/wilycode/database-query) | Build and compile SQL queries. |
| [wilycode/database-driver](https://github.com/wilycode/database-driver) | Driver-independent connection/query contracts. |
| [wilycode/database-driver-pdo](https://github.com/wilycode/database-driver-pdo) | PDO driver and parameter binder. |
| [wilycode/database-driver-mysqli](https://github.com/wilycode/database-driver-mysqli) | MySQLi driver and parameter binder. |
| [wilycode/database-query-executor](https://github.com/wilycode/database-query-executor) | Execute compiled queries using a driver and binder. |
| [wilycode/database-repository](https://github.com/wilycode/database-repository) | Repository integration for model persistence and querying. |

These are independent packages, not runtime dependencies of `database-model`.

## Development

```bash
composer install
composer validate --strict
composer test
```

GitHub Actions runs PHPUnit on PHP 8.4 for pushes and pull requests. Development tests are stored on GitHub but excluded from Composer distribution archives.

## Contributing and license

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) and the [GitHub issue tracker](https://github.com/wilycode/database-model/issues).

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).

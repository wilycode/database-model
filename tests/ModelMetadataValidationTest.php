<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\Attribute\{Table, PrimaryKey, Property, HasMany};
use WilyCode\Database\Model\Model;
use WilyCode\Database\Model\ModelMetadata;
use WilyCode\Database\Model\Tests\Model\Tag;

final class ModelMetadataValidationTest extends TestCase
{
    public function testDuplicateDatabaseColumnIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('same database column "id"');
        ModelMetadata::for(DuplicateColumnModel::class);
    }

    public function testPrivateCollectionSetterIsRejectedDuringMetadataCreation(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a setter');
        ModelMetadata::for(PrivateCollectionSetterModel::class);
    }

    public function testNonModelRelationIsRejectedDuringMetadataCreation(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must reference a subclass');
        ModelMetadata::for(NonModelRelationOwner::class);
    }
}

abstract class MetadataTestModel extends Model
{
    #[PrimaryKey]
    protected ?int $id = null;
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }
}

#[Table]
final class DuplicateColumnModel extends MetadataTestModel
{
    #[Property('id')]
    private ?string $name = null;
    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): void { $this->name = $name; }
}

#[Table]
final class PrivateCollectionSetterModel extends MetadataTestModel
{
    #[HasMany(modelName: Tag::class)]
    private array $tags = [];
    public function getTags(): array { return $this->tags; }
    private function setTags(array $tags): void { $this->tags = $tags; }
}

#[Table]
final class NonModelRelationOwner extends MetadataTestModel
{
    #[HasMany(modelName: \stdClass::class)]
    private array $tags = [];
    public function getTags(): array { return $this->tags; }
    public function setTags(array $tags): void { $this->tags = $tags; }
}

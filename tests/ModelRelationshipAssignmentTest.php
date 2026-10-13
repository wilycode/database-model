<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\Tests\Model\Article;
use WilyCode\Database\Model\Tests\Model\Tag;

final class ModelRelationshipAssignmentTest extends TestCase
{
    public function test_collection_relationship_accepts_identifiers_and_models(): void
    {
        $existingTag = new Tag(['id' => 8]);
        $article = new Article([
            'tags' => ['2', 4, $existingTag],
        ]);

        $tags = $article->getTags();

        self::assertCount(3, $tags);
        self::assertContainsOnlyInstancesOf(Tag::class, $tags);
        self::assertSame([2, 4, 8], array_map(
            static fn (Tag $tag): ?int => $tag->getId(),
            $tags,
        ));
        self::assertTrue($tags[0]->isPersisted());
        self::assertTrue($tags[1]->isPersisted());
    }

    public function test_collection_relationship_rejects_non_scalar_non_array_values(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Relationship "tags" contains an invalid item at index 0.');

        new Article([
            'tags' => [new \stdClass()],
        ]);
    }
}

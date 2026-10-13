<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\ModelMetadata;
use WilyCode\Database\Model\PersistenceAction;
use WilyCode\Database\Model\RelationState;
use WilyCode\Database\Model\Tests\Model\Article;
use WilyCode\Database\Model\Tests\Model\ArticleCategory;
use WilyCode\Database\Model\Tests\Model\Post;
use WilyCode\Database\Model\Tests\Model\User;

final class ModelPublicApiRegressionTest extends TestCase
{
    public function testMetadataCacheAndForeignKeyMapping(): void
    {
        $meta = ModelMetadata::for(Post::class);
        self::assertSame($meta, ModelMetadata::for(Post::class));
        self::assertSame('posts', $meta->getTableName());
        self::assertSame('user_id', $meta->getForeignKeyDbName('user'));
        self::assertSame('user', $meta->mapDbNameToPropertyName('user_id'));
        self::assertSame(['id' => 'id', 'user' => 'user_id', 'title' => 'title'], $meta->getScalarDbProperties());

        ModelMetadata::clearCache();
        self::assertNotSame($meta, ModelMetadata::for(Post::class));
    }

    public function testScalarForeignKeyCreatesReferenceAndExplicitJsonLoadsRelation(): void
    {
        $reference = ModelHydrator::hydrate(Post::class, ['id' => '10', 'user_id' => '3']);
        self::assertSame(10, $reference->getId());
        self::assertSame(3, $reference->getUser()?->getId());
        self::assertSame(RelationState::Reference, $reference->getRelationState('user'));
        self::assertFalse($reference->isRelationLoaded('user'));

        $loaded = ModelHydrator::hydrate(Post::class, [
            'id' => 10,
            'user_id' => 3,
            'user' => '{"id":3,"first_name":"Ada","last_name":"Lovelace"}',
        ]);
        self::assertSame('Ada', $loaded->getUser()?->getFirstName());
        self::assertTrue($loaded->isRelationLoaded('user'));
        self::assertSame(['id' => 10, 'user_id' => 3, 'title' => null], $loaded->asArray(withDbNames: true));
    }

    public function testFullRelationshipWinsWhenForeignKeyComesLast(): void
    {
        $loaded = ModelHydrator::hydrate(Post::class, [
            'id' => 10,
            'user' => ['id' => 3, 'firstName' => 'Ada'],
            'user_id' => 3,
        ]);
        self::assertSame('Ada', $loaded->getUser()?->getFirstName());
        self::assertTrue($loaded->isRelationLoaded('user'));
    }

    public function testChangeTrackingAndSnapshotAfterMutation(): void
    {
        $post = ModelHydrator::hydrate(Post::class, ['id' => 10, 'title' => 'Old']);
        self::assertSame(PersistenceAction::None, $post->getPersistenceAction());
        $post->setTitle('New');
        self::assertSame(PersistenceAction::Update, $post->getPersistenceAction());
        self::assertSame(['title' => 'New'], $post->getUpdateDbValues());
        $post->syncOriginal();
        self::assertSame(PersistenceAction::None, $post->getPersistenceAction());

        $new = new Post(['title' => 'Draft', 'user_id' => 3]);
        self::assertSame(PersistenceAction::Insert, $new->getPersistenceAction());
        self::assertSame(['user_id' => 3, 'title' => 'Draft'], $new->getInsertDbValues());
    }

    public function testExtraDataIsOptInAndNestedSkippingWorks(): void
    {
        $article = new Article([
            'id' => 10,
            'category' => ['id' => 3, 'name' => 'Tech', 'createdBy' => ['id' => 7, 'firstName' => 'Ada']],
        ]);
        $article->setExtraData('score', 0.5);
        self::assertArrayNotHasKey('score', $article->asArray());
        self::assertSame(0.5, $article->asArray(withExtraData: true)['score']);
        self::assertArrayNotHasKey('firstName', $article->asArray(skipNames: ['category.createdBy.firstName'])['category']['createdBy']);
    }
}

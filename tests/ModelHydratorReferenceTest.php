<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\PersistenceAction;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\Tests\Model\Article;
use WilyCode\Database\Model\Tests\Model\ArticleStatus;

final class ModelHydratorReferenceTest extends TestCase
{
    public function test_fk_scalar_column_creates_reference_object_for_foreignkey_relationship_property(): void
    {
        $this->assertSame('category_id', new Article()->metadata()->getScalarDbProperties()['category']);

        // No JOIN / no JSON_OBJECT(...) for category, only FK scalar column.
        $row = [
            'id' => 10,
            'status' => ArticleStatus::Published->value,
            'title' => 'Hello',
            'content' => 'Body',
            'category_id' => 3,
        ];

        /** @var Article $article */
        $article = ModelHydrator::hydrate(Article::class, $row);

        $this->assertTrue($article->isPersisted());
        $this->assertSame(PersistenceAction::None, $article->getPersistenceAction());

        $this->assertNotNull($article->getCategory());
        $this->assertSame(3, $article->getCategory()->getId());

        $this->assertTrue($article->isRelationReference('category'));
        $this->assertFalse($article->isRelationLoaded('category'));

        $db = $article->asArray(withDbNames: true);

        $this->assertSame(3, $db['category_id']);
        $this->assertIsInt($db['category_id']);
        $this->assertArrayNotHasKey('category', $db);
    }

    public function test_persistence_action_switches_to_update_when_model_changes(): void
    {
        $row = [
            'id' => 10,
            'status' => ArticleStatus::Published->value,
            'title' => 'Hello',
            'content' => 'Body',
            'category_id' => 3,
        ];

        /** @var Article $article */
        $article = ModelHydrator::hydrate(Article::class, $row);

        $this->assertSame(PersistenceAction::None, $article->getPersistenceAction());
        $this->assertSame([], $article->getUpdateDbValues());

        $article->setTitle('Hello 2');

        $this->assertSame(PersistenceAction::Update, $article->getPersistenceAction());
        $this->assertSame(['title' => 'Hello 2'], $article->getUpdateDbValues());
    }

    public function test_new_model_is_insert_and_primary_key_is_removed_when_null(): void
    {
        $article = new Article([
            'status' => ArticleStatus::Published->value,
            'title' => 'Hello',
            'content' => 'Body',
            'category_id' => 3,
        ]);

        $this->assertSame(PersistenceAction::Insert, $article->getPersistenceAction());

        $insert = $article->getInsertDbValues();
        $this->assertArrayNotHasKey('id', $insert);
        $this->assertSame(3, $insert['category_id']);
        $this->assertSame('Hello', $insert['title']);
    }
}

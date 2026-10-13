<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\Tests\Model\Article;
use WilyCode\Database\Model\Tests\Model\ArticleCategory;
use WilyCode\Database\Model\Tests\Model\ArticleStatus;
use WilyCode\Database\Model\Tests\Model\JsonDocument;
use WilyCode\Database\Model\Tests\Model\Tag;

final class ModelArrayConverterTest extends TestCase
{
    public function test_asArray_withDbNames_outputs_foreign_key_scalar_instead_of_model_object(): void
    {
        $article = new Article([
            'id' => 10,
            'status' => ArticleStatus::Published->value,
            'title' => 'Hello',
            'content' => 'Body',
            'category' => [
                'id' => 3,
                'name' => 'Tech',
                'niceName' => 'tech',
                'createdBy' => [
                    'id' => 7,
                    'firstName' => 'Ada',
                    'lastName' => 'Lovelace',
                ],
                'updatedBy' => null,
            ],
            'tags' => [
                [
                    'id' => 1,
                    'name' => 'php',
                    'niceName' => 'php',
                    'createdBy' => [
                        'id' => 7,
                        'firstName' => 'Ada',
                        'lastName' => 'Lovelace',
                    ],
                ],
            ],
        ]);

        $db = $article->asArray(withDbNames: true);

        // FK should be a scalar column when persisting / withDbNames.
        $this->assertArrayHasKey('category_id', $db);
        $this->assertSame(3, $db['category_id']);

        // Relations should NOT be embedded as arrays when withDbNames=true.
        $this->assertArrayNotHasKey('category', $db);
        $this->assertArrayNotHasKey('tags', $db);

        // Enum becomes scalar value
        $this->assertSame(ArticleStatus::Published->value, $db['status']);
    }

    public function test_asArray_withoutDbNames_outputs_nested_arrays_for_relations(): void
    {
        $article = new Article([
            'id' => 10,
            'status' => ArticleStatus::Published->value,
            'title' => 'Hello',
            'content' => 'Body',
            'category' => [
                'id' => 3,
                'name' => 'Tech',
                'niceName' => 'tech',
                'createdBy' => [
                    'id' => 7,
                    'firstName' => 'Ada',
                    'lastName' => 'Lovelace',
                ],
                'updatedBy' => null,
            ],
            'tags' => [
                [
                    'id' => 1,
                    'name' => 'php',
                    'niceName' => 'php',
                    'createdBy' => [
                        'id' => 7,
                        'firstName' => 'Ada',
                        'lastName' => 'Lovelace',
                    ],
                ],
                [
                    'id' => 2,
                    'name' => 'mysql',
                    'niceName' => 'mysql',
                    'createdBy' => [
                        'id' => 7,
                        'firstName' => 'Ada',
                        'lastName' => 'Lovelace',
                    ],
                ],
            ],
        ]);

        $arr = $article->asArray();

        $this->assertSame(10, $arr['id']);
        $this->assertSame(ArticleStatus::Published->value, $arr['status']);
        $this->assertSame('Hello', $arr['title']);

        $this->assertIsArray($arr['category']);
        $this->assertSame(3, $arr['category']['id']);
        $this->assertSame('Tech', $arr['category']['name']);

        $this->assertIsArray($arr['category']['createdBy']);
        $this->assertSame('Ada', $arr['category']['createdBy']['firstName']);

        $this->assertIsArray($arr['tags']);
        $this->assertCount(2, $arr['tags']);
        $this->assertSame('php', $arr['tags'][0]['name']);
        $this->assertSame('mysql', $arr['tags'][1]['name']);

        $db = $article->asArray(withDbNames: true);

        // ForeignKey+HasOne property (category) must be output as FK scalar column name.
        $this->assertSame(3, $db['category_id']);
        $this->assertArrayNotHasKey('category', $db);

        // For DB array we do not include nested relations like tags by design.
        $this->assertArrayNotHasKey('tags', $db);

    }

    public function test_hydrator_decodes_json_object_and_array_and_asArray_returns_real_arrays(): void
    {
        $row = [
            'id' => 1,
            // SQL JSON_OBJECT(...)
            'json_object' => '{"foo":1,"bar":{"baz":2}}',
            // SQL JSON_ARRAYAGG(...) / JSON_ARRAY(...)
            'json_array' => '[{"id":10},{"id":20}]',
        ];

        /** @var JsonDocument $doc */
        $doc = ModelHydrator::hydrate(JsonDocument::class, $row, decodeJson: true, mapDbNames: true);

        $this->assertIsArray($doc->getJsonObject());
        $this->assertIsArray($doc->getJsonArray());
        $this->assertSame(['foo' => 1, 'bar' => ['baz' => 2]], $doc->getJsonObject());
        $this->assertSame([['id' => 10], ['id' => 20]], $doc->getJsonArray());

        $arr = $doc->asArray();
        $this->assertIsArray($arr['jsonObject']);
        $this->assertIsArray($arr['jsonArray']);
    }

    public function testModelConstructorAcceptsAnExistingEnumCase(): void
    {
        $article = new Article(['status' => ArticleStatus::Published]);

        $this->assertSame(ArticleStatus::Published, $article->getStatus());
    }

    public function testModelConstructorAcceptsExistingRelationModels(): void
    {
        $category = new ArticleCategory(['id' => 7]);
        $tag = new Tag(['id' => 9]);
        $article = new Article([
            'category' => $category,
            'tags' => [$tag],
        ]);

        $this->assertSame($category, $article->getCategory());
        $this->assertSame([$tag], $article->getTags());
    }
}

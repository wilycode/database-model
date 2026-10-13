<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\ModelMetadata;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\Tests\Model\Article;
use WilyCode\Database\Model\Tests\Model\ArticleStatus;
use WilyCode\Database\Model\Tests\Model\JsonDocument;

final class ModelHydratorJsonTest extends TestCase
{
    public function test_invalid_json_and_scalar_values_have_model_and_field_context(): void
    {
        foreach (['[bad]', '', '42', '"text"', 'true', 'null', null, 42] as $value) {
            try {
                ModelHydrator::hydrate(JsonDocument::class, ['json_array' => $value]);
                self::fail('Invalid non-nullable array value was accepted.');
            } catch (\UnexpectedValueException $error) {
                self::assertStringContainsString(JsonDocument::class, $error->getMessage());
                self::assertStringContainsString('jsonArray', $error->getMessage());
            }
        }
    }

    public function test_it_decodes_json_object_and_json_array_columns_into_php_arrays(): void
    {
        $row = [
            'id' => 1,
            'json_object' => '{"a":1,"b":{"c":2}}',
            'json_array'  => '[{"id":1},{"id":2}]',
        ];

        $doc = ModelHydrator::hydrate(JsonDocument::class, $row);

        $this->assertSame(['a' => 1, 'b' => ['c' => 2]], $doc->getJsonObject());
        $this->assertSame([['id' => 1], ['id' => 2]], $doc->getJsonArray());
    }

    public function test_it_hydrates_relations_when_columns_are_json_object_and_json_array_strings(): void
    {
        $row = [
            'id'      => 10,
            'status'  => 2, // ArticleStatus::Published
            'title'   => 'Hello',
            'content' => 'Body',

            // These two values mimic MySQL JSON columns / JSON_OBJECT / JSON_ARRAYAGG output:
            'category' => '{"id":3,"name":"Tech","niceName":"tech","createdBy":{"id":7,"firstName":"Ada","lastName":"Lovelace"},"updatedBy":null}',
            'tags'     => '[{"id":1,"name":"php","niceName":"php","createdBy":{"id":7,"firstName":"Ada","lastName":"Lovelace"}},{"id":2,"name":"mysql","niceName":"mysql","createdBy":{"id":7,"firstName":"Ada","lastName":"Lovelace"}}]',
        ];

        $article = ModelHydrator::hydrate(Article::class, $row);

        $this->assertSame(10, $article->getId());
        $this->assertSame(ArticleStatus::Published, $article->getStatus());

        $this->assertNotNull($article->getCategory());
        $this->assertSame(3, $article->getCategory()->getId());
        $this->assertSame('Tech', $article->getCategory()->getName());

        $this->assertNotNull($article->getCategory()->getCreatedBy());
        $this->assertSame(7, $article->getCategory()->getCreatedBy()->getId());
        $this->assertSame('Ada', $article->getCategory()->getCreatedBy()->getFirstName());

        $tags = $article->getTags();
        $this->assertCount(2, $tags);

        $this->assertSame(1, $tags[0]->getId());
        $this->assertSame('php', $tags[0]->getName());
        $this->assertSame(7, $tags[0]->getCreatedBy()->getId());

        $this->assertSame(2, $tags[1]->getId());
        $this->assertSame('mysql', $tags[1]->getName());
        $this->assertSame('Lovelace', $tags[1]->getCreatedBy()->getLastName());
    }

    public function test_normalizeRow_can_skip_json_decoding(): void
    {
        $row = [
            'id' => 1,
            'json_object' => '{"a":1}',
            'json_array'  => '[1,2,3]',
        ];

        $attributes = ModelMetadata::for(JsonDocument::class);
        $data = ModelHydrator::normalizeRow($attributes, $row, decodeJson: false, mapDbNames: true);

        // mapDbNames should map json_object -> jsonObject
        $this->assertSame('{"a":1}', $data['jsonObject']);
        $this->assertSame('[1,2,3]', $data['jsonArray']);
    }
}

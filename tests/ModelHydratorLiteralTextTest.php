<?php

declare(strict_types=1);

namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\Tests\Model\{Article, JsonDocument, Tag};

final class ModelHydratorLiteralTextTest extends TestCase
{
    public function test_json_looking_text_is_preserved_at_every_relation_depth(): void
    {
        foreach (['null', '[]', '{"x":1}', '["null"]'] as $text) {
            $article = ModelHydrator::hydrate(Article::class, [
                'id'=>1, 'title'=>$text, 'content'=>$text,
                'tags'=>json_encode([['id'=>2, 'name'=>$text, 'createdBy'=>json_encode(['id'=>3,'firstName'=>$text])]]),
            ]);
            self::assertSame($text, $article->getTitle());
            self::assertSame($text, $article->getContent());
            self::assertSame($text, $article->getTags()[0]->getName());
            self::assertSame($text, $article->getTags()[0]->getCreatedBy()->getFirstName());
        }
    }

    public function test_json_array_properties_preserve_string_elements(): void
    {
        $data = ['null', '[]', '{"id":1}', ['nested'=>'[1]']];
        foreach ([$data, json_encode($data)] as $value) {
            $document = ModelHydrator::hydrate(JsonDocument::class, ['id'=>1,'json_array'=>$value]);
            self::assertSame($data, $document->getJsonArray());
        }
    }

    public function test_mixed_relation_inputs_keep_models_references_and_literal_text(): void
    {
        $model = new Tag(['id'=>2,'name'=>'[]']);
        $article = new Article(['tags'=>[$model, '3', ['id'=>4,'name'=>'null'], '{"id":5,"name":"[1]"}']]);
        self::assertSame($model, $article->getTags()[0]);
        self::assertSame([2,3,4,5], array_map(static fn (Tag $tag): ?int => $tag->getId(), $article->getTags()));
        self::assertSame('null', $article->getTags()[2]->getName());
        self::assertSame('[1]', $article->getTags()[3]->getName());
        $this->expectException(\InvalidArgumentException::class);
        $article->setRelationship('tags', [new JsonDocument(['id'=>7])]);
    }
}

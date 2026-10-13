<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Database\Model\ModelHydrator;
use WilyCode\Database\Model\Tests\Model\Article;

/**
 * MariaDB 10.4 (and some custom fetchers) can return nested JSON objects as strings.
 * Example: a JSON array of tags where each tag has "createdBy" => '{"id":1,...}'.
 */
final class ModelHydratorMariaDbNestedJsonTest extends TestCase
{
    public function test_it_decodes_nested_json_strings_inside_already_decoded_arrays(): void
    {
        $row = [
            'id' => 10,
            'status' => 2,
            'title' => 'Hello',
            'content' => 'Body',
            // Tags already decoded (array), but nested relation is still a JSON string.
            'tags' => [
                [
                    'id' => 1,
                    'name' => 'php',
                    'niceName' => 'php',
                    'createdBy' => '{"id":7,"firstName":"Ada","lastName":"Lovelace"}',
                ],
            ],
        ];

        /** @var Article $article */
        $article = ModelHydrator::hydrate(Article::class, $row, decodeJson: true);

        $tags = $article->getTags();
        $this->assertCount(1, $tags);

        $this->assertNotNull($tags[0]->getCreatedBy());
        $this->assertSame(7, $tags[0]->getCreatedBy()->getId());
        $this->assertSame('Ada', $tags[0]->getCreatedBy()->getFirstName());
    }

    public function test_it_decodes_nested_json_strings_inside_json_string_columns(): void
    {
        // This mimics a DB string output where nested relation value is a JSON string.
        $tagsJson = '[{"id":1,"name":"php","niceName":"php","createdBy":"{\\"id\\":7,\\"firstName\\":\\"Ada\\",\\"lastName\\":\\"Lovelace\\"}"}]';

        $row = [
            'id' => 10,
            'status' => 2,
            'title' => 'Hello',
            'content' => 'Body',
            'tags' => $tagsJson,
        ];

        /** @var Article $article */
        $article = ModelHydrator::hydrate(Article::class, $row, decodeJson: true);

        $tags = $article->getTags();
        $this->assertCount(1, $tags);
        $this->assertNotNull($tags[0]->getCreatedBy());
        $this->assertSame(7, $tags[0]->getCreatedBy()->getId());
        $this->assertSame('Lovelace', $tags[0]->getCreatedBy()->getLastName());
    }

    public function test_full_nested_relation_wins_when_foreign_key_column_is_returned_after_it(): void
    {
        $row = [
            'id' => 10,
            'status' => 2,
            'title' => 'Hello',
            'content' => 'Body',
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
                    'created_by_id' => 7,
                ],
            ],
        ];

        /** @var Article $article */
        $article = ModelHydrator::hydrate(Article::class, $row, decodeJson: true);

        $createdBy = $article->getTags()[0]->getCreatedBy();

        $this->assertNotNull($createdBy);
        $this->assertSame(7, $createdBy->getId());
        $this->assertSame('Ada', $createdBy->getFirstName());
        $this->assertSame('Lovelace', $createdBy->getLastName());
        $this->assertTrue($article->getTags()[0]->isRelationLoaded('createdBy'));
    }
}

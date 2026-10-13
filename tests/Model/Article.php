<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests\Model;



use WilyCode\Database\Model\Attribute\ForeignKey;
use WilyCode\Database\Model\Attribute\HasManyToMany;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\PrimaryKey;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Attribute\Table;
use WilyCode\Database\Model\Model;

#[Table]
class Article extends Model
{
    #[PrimaryKey]
    private ?int $id = null;

    #[Property]
    private ?ArticleStatus $status = null;

    #[HasOne, ForeignKey]
    private ?ArticleCategory $category = null;

    #[Property]
    private ?string $title = null;

    #[Property]
    private ?string $content = null;

    #[HasManyToMany(modelName: Tag::class)]
    private array $tags = [];

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): Article
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(Tag $tag): Article
    {
        $this->tags[] = $tag;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): Article
    {
        $this->content = $content;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): Article
    {
        $this->title = $title;
        return $this;
    }

    public function getCategory(): ?ArticleCategory
    {
        return $this->category;
    }

    public function setCategory(?ArticleCategory $category): Article
    {
        $this->category = $category;
        return $this;
    }

    public function getStatus(): ?ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(?ArticleStatus $status): Article
    {
        $this->status = $status;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): Article
    {
        $this->id = $id;
        return $this;
    }

}
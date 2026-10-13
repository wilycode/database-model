<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests\Model;

use WilyCode\Database\Model\Attribute\ForeignKey;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\PrimaryKey;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Attribute\Table;
use WilyCode\Database\Model\Model;

#[Table('posts')]
final class Post extends Model
{
    #[PrimaryKey]
    private ?int $id = null;

    /**
     * Relationship property that is ALSO a FK column in DB (posts.user_id).
     * This is exactly the case where asArray(withDbNames:true) should output scalar user_id.
     */
    #[ForeignKey]
    #[HasOne]
    private ?User $user = null;

    #[Property]
    private ?string $title = null;

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): void { $this->user = $user; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): void { $this->title = $title; }
}

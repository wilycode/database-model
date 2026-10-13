<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests\Model;


use WilyCode\Database\Model\Attribute\ForeignKey;
use WilyCode\Database\Model\Attribute\HasOne;
use WilyCode\Database\Model\Attribute\PrimaryKey;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Attribute\Table;
use WilyCode\Database\Model\Model;

#[Table]
class Tag extends Model
{
    #[PrimaryKey]
    private ?int $id = null;

    #[Property]
    private ?string $name = null;

    #[Property]
    private ?string $niceName = null;

    #[HasOne, ForeignKey]
    private ?User $createdBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getNiceName(): ?string
    {
        return $this->niceName;
    }

    public function setNiceName(?string $niceName): void
    {
        $this->niceName = $niceName;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): Tag
    {
        $this->createdBy = $createdBy;
        return $this;
    }

}
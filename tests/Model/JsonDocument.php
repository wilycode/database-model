<?php

declare(strict_types=1);


namespace WilyCode\Database\Model\Tests\Model;

use WilyCode\Database\Model\Attribute\PrimaryKey;
use WilyCode\Database\Model\Attribute\Property;
use WilyCode\Database\Model\Attribute\Table;
use WilyCode\Database\Model\Model;

#[Table('documents')]
final class JsonDocument extends Model
{
    #[PrimaryKey]
    private ?int $id = null;

    #[Property]
    private array $jsonObject = [];

    #[Property]
    private array $jsonArray = [];

    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): void { $this->id = $id; }

    public function getJsonObject(): array { return $this->jsonObject; }
    public function setJsonObject(array $jsonObject): void { $this->jsonObject = $jsonObject; }

    public function getJsonArray(): array { return $this->jsonArray; }
    public function setJsonArray(array $jsonArray): void { $this->jsonArray = $jsonArray; }
}

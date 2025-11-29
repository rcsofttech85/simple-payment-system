<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\ApiResource\DTO\TransferRead;
use App\ApiResource\DTO\TransferRequest;
use App\ApiResource\Processor\TransferProcessor;
use App\Enum\TransferStatus;
use App\Repository\TransferRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidV7Generator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/transfers',
            input: TransferRequest::class,
            output: TransferRead::class,
            processor: TransferProcessor::class
        )
    ]
)]

class Transfer
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", length:36, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidV7Generator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(inversedBy: 'outgoingTransfers')]
    private ?Account $fromAccount = null;

    #[ORM\ManyToOne(inversedBy: 'incomingTransfers')]
    private ?Account $toAccount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column(enumType: TransferStatus::class)]
    private TransferStatus $status;

    #[ORM\Column(length: 64)]
    private ?string $idempotencyKey = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {

        $this->status = TransferStatus::PENDING;
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }

    public function getFromAccount(): ?Account
    {
        return $this->fromAccount;
    }

    public function setFromAccount(?Account $fromAccount): static
    {
        $this->fromAccount = $fromAccount;

        return $this;
    }

    public function getToAccount(): ?Account
    {
        return $this->toAccount;
    }

    public function setToAccount(?Account $toAccount): static
    {
        $this->toAccount = $toAccount;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStatus(): TransferStatus
    {
        return $this->status;
    }
    public function setStatus(TransferStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}

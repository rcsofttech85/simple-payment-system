<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BalanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidV7Generator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[ORM\Entity(repositoryClass: BalanceRepository::class)]
class Balance
{
    #[ORM\Id]
    #[ORM\Column(type: "uuid", length:36, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(class: UuidV7Generator::class)]
    private ?UuidInterface $id = null;


    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Account $account = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 2)]
    private ?string $available = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;




    public function getId(): ?UuidInterface
    {
        return $this->id;
    }


    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getAvailable(): ?string
    {
        return $this->available;
    }

    public function setAvailable(string $available): static
    {
        $this->available = $available;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function debit(string $amount): void
    {
        if (bccomp($this->available, $amount, 2) < 0) {
            throw new UnprocessableEntityHttpException('Insufficient funds');
        }
        $this->available = bcsub($this->available, $amount, 2);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function credit(string $amount): void
    {
        $this->available = bcadd($this->available, $amount, 2);
        $this->updatedAt = new \DateTimeImmutable();
    }
}

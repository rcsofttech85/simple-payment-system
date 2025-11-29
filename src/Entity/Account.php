<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidType;
use Ramsey\Uuid\Doctrine\UuidV7Generator;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, length:36, unique: true)]
    #[ORM\GeneratedValue(strategy: "CUSTOM")]
    #[ORM\CustomIdGenerator(
        class: UuidV7Generator::class
    )]
    private ?UuidInterface $id = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Transfer>
     */
    #[ORM\OneToMany(targetEntity: Transfer::class, mappedBy: 'fromAccount')]
    private Collection $outgoingTransfers;

    #[ORM\OneToMany(targetEntity: Transfer::class, mappedBy: 'toAccount')]
    private Collection $incomingTransfers;


    public function __construct()
    {

        $this->outgoingTransfers = new ArrayCollection();
        $this->incomingTransfers = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?UuidInterface
    {
        return $this->id;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Transfer>
     */
    public function getIncomingTransfer(): Collection
    {
        return $this->incomingTransfers;
    }

    public function addIncomingTransfer(Transfer $transfer): static
    {
        if (!$this->incomingTransfers->contains($transfer)) {
            $this->incomingTransfers->add($transfer);
            $transfer->setToAccount($this);
        }

        return $this;
    }

    public function removeIncomingTransfer(Transfer $transfer): static
    {
        if ($this->incomingTransfers->removeElement($transfer)) {

            if ($transfer->getToAccount() === $this) {
                $transfer->setToAccount(null);
            }
        }

        return $this;
    }



    /**
     * @return Collection<int, Transfer>
     */
    public function getOutgoingTransfer(): Collection
    {
        return $this->outgoingTransfers;
    }

    public function addOutgoingTransfer(Transfer $transfer): static
    {
        if (!$this->outgoingTransfers->contains($transfer)) {
            $this->outgoingTransfers->add($transfer);
            $transfer->setFromAccount($this);
        }

        return $this;
    }

    public function removeOutgoingTransfer(Transfer $transfer): static
    {
        if ($this->outgoingTransfers->removeElement($transfer)) {
            // set the owning side to null (unless already changed)
            if ($transfer->getFromAccount() === $this) {
                $transfer->setFromAccount(null);
            }
        }

        return $this;
    }
}

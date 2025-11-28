<?php

declare(strict_types=1);

namespace App\ApiResource\Processor;

use App\Entity\Account;
use App\Entity\Balance;
use App\Entity\Transfer;
use Doctrine\ORM\EntityManagerInterface;

final class TransferProcessor
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function transfer(Account $from, Account $to, string $amount, string $currency, string $idempotencyKey): Transfer
    {
        $fromBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->em->beginTransaction();
        try {
            $fromBalance->debit($amount);
            $toBalance->credit($amount);

            $transfer = new Transfer($from, $to, $amount, $currency, $idempotencyKey);
            $this->em->persist($transfer);
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $transfer;
    }
}

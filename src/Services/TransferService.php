<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Account;
use App\Entity\Balance;
use App\Entity\Transfer;
use App\Enum\TransferStatus;
use App\Event\TransferCompletedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TransferService
{
    private const TRANSFER_PREFIX = 'transfer_';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IdempotencyService $idempotency,
        private readonly LockService $lock,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly Security $security
    ) {
    }

    public function processTransfer(
        string $fromAccountId,
        string $toAccountId,
        string $amount,
        string $currency,
        string $idempotencyKey
    ): Transfer {

        // IDEMPOTENCY CHECK
        if ($existing = $this->idempotency->getResponse($idempotencyKey)) {
            return $this->loadTransfer($existing['id']);
        }

        $from = $this->getAccount($fromAccountId, 'From account not found');


        if ($this->security->getUser() !== $from->getUser()) {
            throw new AccessDeniedHttpException("Invalid Sender Account");
        }
        $to   = $this->getAccount($toAccountId, 'To account not found');

        // LOCK PREVENT DOUBLE SPENDING
        $lockKey = self::TRANSFER_PREFIX . $from->getId();
        if (!$this->lock->acquireLock($lockKey, 10)) {
            throw new \DomainException("Transfer is already in progress for this account");
        }

        try {
            $this->em->beginTransaction();

            $fromBalance = $this->loadBalance($from);
            $toBalance   = $this->loadBalance($to);

            $fromBalance->debit($amount);
            $toBalance->credit($amount);

            $transfer = $this->createTransfer($from, $to, $amount, $currency, $idempotencyKey);

            $this->em->commit();

            // save idempotency
            $this->idempotency->storeResponse($idempotencyKey, [
                'id' => $transfer->getId()->toString()
            ]);

            // dispatch async-ready event
            $this->dispatcher->dispatch(
                new TransferCompletedEvent($transfer),
                TransferCompletedEvent::NAME
            );

            return $transfer;

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        } finally {
            $this->lock->releaseLock($lockKey);
        }
    }

    private function getAccount(string $id, string $errorMessage): Account
    {
        $account = $this->em->getRepository(Account::class)->find($id);

        if (!$account) {
            throw new NotFoundHttpException($errorMessage);
        }

        return $account;
    }

    private function loadBalance(Account $account): Balance
    {
        return $this->em->getRepository(Balance::class)->findOneBy([
            'account' => $account
        ]);
    }

    private function createTransfer(Account $from, Account $to, string $amount, string $currency, string $key): Transfer
    {
        $transfer = new Transfer();
        $transfer->setFromAccount($from);
        $transfer->setToAccount($to);
        $transfer->setAmount($amount);
        $transfer->setCurrency($currency);
        $transfer->setStatus(TransferStatus::COMPLETED);
        $transfer->setIdempotencyKey($key);
        $transfer->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($transfer);
        $this->em->flush();

        return $transfer;
    }

    private function loadTransfer(string $id): Transfer
    {
        return $this->em->getRepository(Transfer::class)->find($id);
    }
}

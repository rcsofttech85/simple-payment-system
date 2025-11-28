<?php

declare(strict_types=1);

namespace App\ApiResource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\DTO\TransferRead;
use App\ApiResource\DTO\TransferRequest;
use App\Entity\Account;
use App\Entity\Balance;
use App\Entity\Transfer;
use App\Enum\TransferStatus;
use App\Services\IdempotencyService;
use App\Services\RedisLockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransferProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IdempotencyService $idempotency,
        private readonly RedisLockService $lock
    ) {
    }

    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): TransferRead {

        /** @var TransferRequest $data */


        //  IDEMPOTENCY CHECK

        if ($existing = $this->idempotency->getResponse($data->idempotencyKey)) {
            $transfer = $this->em->getRepository(Transfer::class)->find($existing['id']);
            return new TransferRead(
                $transfer->getId()->toString(),
                $transfer->getFromAccount()->getId()->toString(),
                $transfer->getToAccount()->getId()->toString(),
                $transfer->getAmount(),
                $transfer->getCurrency(),
                $transfer->getStatus()->value,
                $transfer->getCreatedAt()
            );
        }

        $from = $this->em->getRepository(Account::class)->find($data->fromAccountId);
        $to   = $this->em->getRepository(Account::class)->find($data->toAccountId);

        if (!$from) {
            throw new NotFoundHttpException("From account not found");
        }
        if (!$to) {
            throw new NotFoundHttpException("To account not found");
        }


        //  REDIS LOCK (PREVENT DOUBLE SPENDING)

        $lockKey = "transfer_lock:" . $from->getId();

        if (!$this->lock->acquireLock($lockKey, 10)) {
            throw new ConflictHttpException("Transfer is already in progress for this account");
        }


        //  BEGIN BUSINESS LOGIC

        try {
            $this->em->beginTransaction();

            $fromBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
            $toBalance   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

            $fromBalance->debit($data->amount);
            $toBalance->credit($data->amount);

            $transfer = new Transfer();
            $transfer->setFromAccount($from);
            $transfer->setToAccount($to);
            $transfer->setAmount($data->amount);
            $transfer->setCurrency($data->currency);
            $transfer->setStatus(TransferStatus::COMPLETED);
            $transfer->setIdempotencyKey($data->idempotencyKey);
            $transfer->setCreatedAt(new \DateTimeImmutable());

            $this->em->persist($transfer);
            $this->em->flush();
            $this->em->commit();


            // STORE IDEMPOTENCY RESPONSE

            $this->idempotency->storeResponse(
                $data->idempotencyKey,
                ['id' => $transfer->getId()->toString()],
            );

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        } finally {

            // ALWAYS RELEASE LOCK
            $this->lock->releaseLock($lockKey);
        }


        //RETURN API DTO

        return new TransferRead(
            $transfer->getId()->toString(),
            $from->getId()->toString(),
            $to->getId()->toString(),
            $transfer->getAmount(),
            $transfer->getCurrency(),
            $transfer->getStatus()->value,
            $transfer->getCreatedAt()
        );
    }
}

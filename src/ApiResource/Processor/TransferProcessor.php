<?php

declare(strict_types=1);

namespace App\ApiResource\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\DTO\TransferRead;
use App\ApiResource\DTO\TransferRequest;
use App\Services\TransferService;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransferProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly TransferService $transferService
    ) {
    }

    public function process(
        $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = []
    ): TransferRead {
        /** @var TransferRequest $data */

        try {
            $transfer = $this->transferService->processTransfer(
                $data->fromAccountId,
                $data->toAccountId,
                $data->amount,
                $data->currency,
                $data->idempotencyKey
            );
        } catch (\DomainException $e) {
            throw new ConflictHttpException($e->getMessage());
        }

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
}

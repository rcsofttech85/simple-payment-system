<?php

namespace App\EventSubscriber;

use App\Event\TransferCompletedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class TransferEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TransferCompletedEvent::NAME => 'onTransferCompleted',
        ];
    }

    public function onTransferCompleted(TransferCompletedEvent $event): void
    {

        $transfer = $event->getTransfer();
        $this->logger->info('Transfer completed', [
            'transfer_id'  => $transfer->getId()->toString(),
            'from_account' => $transfer->getFromAccount()->getId()->toString(),
            'to_account'   => $transfer->getToAccount()->getId()->toString(),
            'amount'       => (string) $transfer->getAmount(),
            'completed_at' => new \DateTimeImmutable()->format(DATE_ATOM),
        ]);


    }
}

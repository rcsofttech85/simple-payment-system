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
        $this->logger->info(sprintf(
            'Transfer completed: %s → %s Amount: %s %s',
            $transfer->getFromAccount()->getId(),
            $transfer->getToAccount()->getId(),
            $transfer->getAmount(),
            $transfer->getCurrency()
        ));


    }
}

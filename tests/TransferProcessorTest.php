<?php

declare(strict_types=1);

namespace App\Tests\ApiResource\Processor;

use ApiPlatform\Metadata\Post;
use App\ApiResource\DTO\TransferRequest;
use App\ApiResource\Processor\TransferProcessor;
use App\Entity\Account;
use App\Entity\Balance;
use App\Entity\Transfer;
use App\Enum\TransferStatus;
use App\Services\IdempotencyService;
use App\Services\LockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class TransferProcessorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private IdempotencyService $idempotency;
    private LockService $lock;
    private TransferProcessor $processor;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine.orm.entity_manager');

        // REAL LOCK SYSTEM
        $store = new FlockStore(sys_get_temp_dir());
        $factory = new LockFactory($store);

        $this->lock = new LockService($factory);

        // Use in-memory idempotency only (fine)
        $cache = new ArrayAdapter();
        $this->idempotency = new IdempotencyService($cache);

        $this->processor = new TransferProcessor($this->em, $this->idempotency, $this->lock);
    }


    private function createAccountWithBalance(string $amount = '1000.00'): Account
    {
        $account = new Account();
        $account->setCurrency('USD');
        $this->em->persist($account);
        $this->em->flush();

        $balance = new Balance();
        $balance->setAccount($account);
        $balance->setAvailable($amount);
        $balance->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($balance);
        $this->em->flush();

        return $account;
    }

    public function testSuccessfulTransfer(): void
    {
        $from = $this->createAccountWithBalance('1000.00');
        $to   = $this->createAccountWithBalance('0.00');

        $request = new TransferRequest(
            fromAccountId: $from->getId()->toString(),
            toAccountId: $to->getId()->toString(),
            amount: '100.00',
            currency: 'USD',
            idempotencyKey: 'test-key'
        );

        $result = $this->processor->process($request, new Post());


        // Refresh balances
        $fromBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBalance   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('900.00', $fromBalance->getAvailable());
        $this->assertSame('100.00', $toBalance->getAvailable());

        $this->assertSame('USD', $result->currency);
        $this->assertSame(TransferStatus::COMPLETED->value, $result->status);

        $transfer = $this->em->getRepository(Transfer::class)->find($result->id);
        $this->assertNotNull($transfer);
        $this->assertSame('100.00', $transfer->getAmount());
        $this->assertSame(TransferStatus::COMPLETED, $transfer->getStatus());
    }

    public function testIdempotentTransferReturnsSameResult(): void
    {
        $from = $this->createAccountWithBalance('500.00');
        $to   = $this->createAccountWithBalance('100.00');

        $idempotencyKey = 'idem-test-key';

        $request = new TransferRequest(
            fromAccountId: $from->getId()->toString(),
            toAccountId: $to->getId()->toString(),
            amount: '50.00',
            currency: 'USD',
            idempotencyKey: $idempotencyKey
        );

        // First call: creates the transfer
        $firstResult = $this->processor->process($request, new Post());

        // Capture balances after first transfer
        $fromBalance1 = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBalance1   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('450.00', $fromBalance1->getAvailable());
        $this->assertSame('150.00', $toBalance1->getAvailable());

        // Second call: should return the same transfer and not change balances
        $secondResult = $this->processor->process($request, new Post());

        $fromBalance2 = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBalance2   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame($firstResult->id, $secondResult->id, 'Idempotent call returns same transfer ID');
        $this->assertSame('450.00', $fromBalance2->getAvailable(), 'Sender balance unchanged on idempotent call');
        $this->assertSame('150.00', $toBalance2->getAvailable(), 'Recipient balance unchanged on idempotent call');

        // Verify only one transfer exists in DB
        $transfers = $this->em->getRepository(Transfer::class)->findBy(['idempotencyKey' => $idempotencyKey]);
        $this->assertCount(1, $transfers);
    }

    public function testInsufficientBalance(): void
    {
        // Sender has only 50
        $from = $this->createAccountWithBalance('50.00');

        // Receiver balance
        $to   = $this->createAccountWithBalance('0.00');

        $request = new TransferRequest(
            fromAccountId: $from->getId()->toString(),
            toAccountId: $to->getId()->toString(),
            amount: '100.00',  // MORE than available
            currency: 'USD',
            idempotencyKey: 'insufficient-1'
        );

        $this->expectException(\RuntimeException::class); // or your custom exception

        try {
            $this->processor->process($request, new Post());
        } finally {
            // Verify balances are untouched
            $fromBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
            $toBalance   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

            $this->assertSame('50.00', $fromBalance->getAvailable());
            $this->assertSame('0.00', $toBalance->getAvailable());

            // No transfer created
            $transfers = $this->em->getRepository(Transfer::class)->findBy([
                'idempotencyKey' => 'insufficient-1'
            ]);

            $this->assertCount(0, $transfers, 'No transfer should be created when funds are insufficient');
        }
    }

    public function testTransferFailsWhenConcurrentLockIsHeld(): void
    {
        $from = $this->createAccountWithBalance('1000.00');
        $to   = $this->createAccountWithBalance('0.00');

        $idempotencyKey = 'lock-test-key';

        // Manually acquire the lock to simulate concurrent request
        $lockKey = 'transfer_' . $from->getId()->toString();
        $this->lock->acquireLock($lockKey, 30); // lock is now held

        $request = new TransferRequest(
            fromAccountId: $from->getId()->toString(),
            toAccountId: $to->getId()->toString(),
            amount: '100.00',
            currency: 'USD',
            idempotencyKey: $idempotencyKey
        );

        // Expect the processor to throw ConflictHttpException
        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Transfer is already in progress for this account');

        $this->processor->process($request, new Post());

        // Verify balances remain unchanged
        $fromBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBalance   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('1000.00', $fromBalance->getAvailable());
        $this->assertSame('0.00', $toBalance->getAvailable());

        // No transfer record created
        $transfers = $this->em->getRepository(Transfer::class)->findBy([
            'idempotencyKey' => $idempotencyKey
        ]);
        $this->assertCount(0, $transfers);
    }




}

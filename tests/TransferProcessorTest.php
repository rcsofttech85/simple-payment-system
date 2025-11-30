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
use App\Services\TransferService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TransferProcessorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private IdempotencyService $idempotency;
    private LockService $lock;
    private TransferService $service;
    private TransferProcessor $processor;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine.orm.entity_manager');


        // Lock system (real lock)
        $store = new FlockStore(sys_get_temp_dir());
        $factory = new LockFactory($store);
        $this->lock = new LockService($factory);

        // Idempotency uses array cache
        $this->idempotency = new IdempotencyService(new ArrayAdapter());

        // mocked
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $security = $this->createMock(Security::class);

        $this->service = new TransferService(
            $this->em,
            $this->idempotency,
            $this->lock,
            $dispatcher,
            $security
        );

        $this->processor = new TransferProcessor($this->service);
    }

    private function createAccount(string $amount): Account
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

    // ----------------------------------------------------
    // SUCCESSFUL TRANSFER
    // ----------------------------------------------------
    public function testSuccessfulTransfer(): void
    {
        $from = $this->createAccount('1000.00');
        $to   = $this->createAccount('0.00');

        $request = new TransferRequest(
            $from->getId()->toString(),
            $to->getId()->toString(),
            '100.00',
            'USD',
            'test-key'
        );

        $result = $this->processor->process($request, new Post());

        $fromBal = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBal   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('900.00', $fromBal->getAvailable());
        $this->assertSame('100.00', $toBal->getAvailable());

        $this->assertSame('USD', $result->currency);
        $this->assertSame(TransferStatus::COMPLETED->value, $result->status);
    }

    // ----------------------------------------------------
    // IDEMPOTENCY
    // ----------------------------------------------------
    public function testIdempotentTransferReturnsSameResult(): void
    {
        $from = $this->createAccount('500.00');
        $to   = $this->createAccount('100.00');

        $key = 'idem-key-1';

        $request = new TransferRequest(
            $from->getId()->toString(),
            $to->getId()->toString(),
            '50.00',
            'USD',
            $key
        );

        // First transfer
        $first = $this->processor->process($request, new Post());

        // Capture balances
        $from1 = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $to1   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('450.00', $from1->getAvailable());
        $this->assertSame('150.00', $to1->getAvailable());

        // Second call (should be idempotent)
        $second = $this->processor->process($request, new Post());

        $this->assertSame($first->id, $second->id);

        // Balances unchanged
        $from2 = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $to2   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('450.00', $from2->getAvailable());
        $this->assertSame('150.00', $to2->getAvailable());
    }

    // ----------------------------------------------------
    // INSUFFICIENT FUNDS
    // ----------------------------------------------------
    public function testInsufficientBalance(): void
    {
        $from = $this->createAccount('50.00');
        $to   = $this->createAccount('0.00');

        $request = new TransferRequest(
            $from->getId()->toString(),
            $to->getId()->toString(),
            '100.00', // too much
            'USD',
            'insufficient-key'
        );

        $this->expectException(UnprocessableEntityHttpException::class);

        try {
            $this->processor->process($request, new Post());
        } finally {
            $fromBal = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
            $toBal   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

            $this->assertSame('50.00', $fromBal->getAvailable());
            $this->assertSame('0.00', $toBal->getAvailable());
        }
    }

    // ----------------------------------------------------
    // LOCK PREVENTS DOUBLE SPEND
    // ----------------------------------------------------
    public function testTransferFailsWhenConcurrentLockIsHeld(): void
    {
        $from = $this->createAccount('1000.00');
        $to   = $this->createAccount('0.00');

        // lock key changed → transfer_<id>
        $lockKey = 'transfer_' . $from->getId()->toString();
        $this->lock->acquireLock($lockKey, 10);

        $request = new TransferRequest(
            $from->getId()->toString(),
            $to->getId()->toString(),
            '100.00',
            'USD',
            'lock-key-1'
        );

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Transfer is already in progress');

        $this->processor->process($request, new Post());

        // verify balances unchanged
        $fromBal = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBal   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $this->assertSame('1000.00', $fromBal->getAvailable());
        $this->assertSame('0.00', $toBal->getAvailable());
    }
}

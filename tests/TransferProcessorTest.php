<?php

declare(strict_types=1);

namespace App\Tests\Processor;

use ApiPlatform\Metadata\Post;
use App\ApiResource\DTO\TransferRequest;
use App\ApiResource\Processor\TransferProcessor;
use App\Entity\Account;
use App\Entity\Balance;
use App\Entity\Transfer;
use App\Enum\TransferStatus;
use App\Services\IdempotencyService;
use App\Services\RedisLockService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TransferProcessorTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    /** @var Client&MockObject */
    private $redisMock;

    private IdempotencyService $idempotency;
    private RedisLockService $lock;
    private TransferProcessor $processor;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine.orm.entity_manager');

        // Mock Redis
        $this->redisMock = $this->createMock(Client::class);

        $this->idempotency = new IdempotencyService($this->redisMock);
        $this->lock        = new RedisLockService($this->redisMock);

        $this->processor = new TransferProcessor(
            $this->em,
            $this->idempotency,
            $this->lock
        );
    }

    public function testSuccessfulTransfer(): void
    {
        $from = $this->createAccountWithBalance('1000.00');
        $to   = $this->createAccountWithBalance('0.00');

        $fromBalance = $this->em->getRepository(Balance::class)->findOneBy(['account' => $from]);
        $toBalance   = $this->em->getRepository(Balance::class)->findOneBy(['account' => $to]);

        $lockKey = "transfer_lock:" . $from->getId();

        // 1️⃣ Redis LOCK succeeds
        $this->redisMock
            ->expects($this->atLeastOnce())
            ->method('__call')
            ->willReturnCallback(function ($command, $args) use ($lockKey) {

                if ($command === 'set') {
                    // Lock acquisition
                    $this->assertEquals($lockKey, $args[0]);
                    $this->assertEquals('locked', $args[1]);
                    $this->assertEquals('NX', $args[2]);
                    $this->assertEquals('EX', $args[3]);
                    $this->assertEquals(10, $args[4]);
                    return "OK";
                }

                if ($command === 'get') {
                    // idempotency cache miss
                    $this->assertEquals("idempotency:test-key", $args[0]);
                    return null;
                }

                if ($command === 'setex') {
                    // store idempotency response
                    $this->assertEquals("idempotency:test-key", $args[0]);
                    $this->assertEquals(3600, $args[1]);
                    return true;
                }

                if ($command === 'del') {
                    // release lock
                    $this->assertEquals([$lockKey], $args[0]);
                    return 1;
                }

                return null;
            });

        // ACT
        $response = $this->processor->process(
            new TransferRequest(
                $from->getId()->toString(),
                $to->getId()->toString(),
                '100.00',
                'USD',
                'test-key'
            ),
            new Post()
        );

        // ASSERT
        $this->assertEquals('900.00', $fromBalance->getAvailable());
        $this->assertEquals('100.00', $toBalance->getAvailable());
        $this->assertEquals('USD', $response->currency);
        $this->assertEquals(TransferStatus::COMPLETED->value, $response->status);
    }


    private function createAccountWithBalance(string $amount): Account
    {
        $account = new Account();
        $account->setCurrency('INR');
        $balance = new Balance();
        $balance->setUpdatedAt(new DateTimeImmutable());

        $balance->setAccount($account);
        $balance->setAvailable($amount);

        $this->em->persist($account);
        $this->em->persist($balance);
        $this->em->flush();

        return $account;
    }

    public function testFailsWhenRedisLockFails(): void
    {
        $from = $this->createAccountWithBalance('300.00');
        $to   = $this->createAccountWithBalance('100.00');

        // Mock Redis LOCK failing
        $this->redisMock
            ->method('executeCommand')
            ->willReturn(null); // lock acquisition fails

        $this->expectException(ConflictHttpException::class);

        $this->processor->process(
            new TransferRequest(
                $from->getId()->toString(),
                $to->getId()->toString(),
                '10.00',
                'USD',
                'lk-fail'
            ),
            new Post()
        );
    }

    public function testIdempotentRequestReturnsSameResult(): void
    {
        $from = $this->createAccountWithBalance('500.00');
        $to   = $this->createAccountWithBalance('50.00');

        $transfer = new Transfer();
        $transfer->setFromAccount($from);
        $transfer->setToAccount($to);
        $transfer->setAmount('100.00');
        $transfer->setCurrency('USD');
        $transfer->setStatus(TransferStatus::COMPLETED);
        $transfer->setIdempotencyKey('idem-123');
        $transfer->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($transfer);
        $this->em->flush();

        // Redis returns existing idempotency result
        $this->redisMock
            ->expects($this->once())
            ->method('__call')
            ->with(
                $this->equalTo('get'),
                $this->equalTo(['idempotency:idem-123'])
            )
            ->willReturn(json_encode([
                'id' => $transfer->getId()->toString()
            ]));

        // Act
        $response = $this->processor->process(
            new TransferRequest(
                fromAccountId: $from->getId()->toString(),
                toAccountId: $to->getId()->toString(),
                amount: '100.00',
                currency: 'USD',
                idempotencyKey: 'idem-123'
            ),
            new Post()
        );

        // Assert
        $this->assertEquals($transfer->getId()->toString(), $response->id);
    }

}

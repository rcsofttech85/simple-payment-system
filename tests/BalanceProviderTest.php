<?php

declare(strict_types=1);

namespace App\Tests\ApiResource\Provider;

use ApiPlatform\Metadata\Operation;
use App\ApiResource\DTO\BalanceRead;
use App\ApiResource\Provider\BalanceProvider;
use App\Entity\Balance;
use App\Entity\User;
use App\Services\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class BalanceProviderTest extends KernelTestCase
{
    private EntityManagerInterface&MockObject $emMock;
    private CacheInterface&MockObject $redisCacheMock;
    private BalanceProvider $provider;
    private EntityRepository&MockObject $repositoryMock;

    protected function setUp(): void
    {

        // 1. Mock the Doctrine Repository
        $this->repositoryMock = $this->createMock(EntityRepository::class);


        // 2. Mock the Entity Manager
        $this->emMock = $this->createMock(EntityManagerInterface::class);
        $this->emMock
            ->method('getRepository')
            ->with(Balance::class)
            ->willReturn($this->repositoryMock);

        // 3. Mock the Cache
        $this->redisCacheMock = $this->createMock(CacheInterface::class);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->mockUser());


        $limiter = $this->createMock(RateLimiterService::class);

        // 4. Instantiate the Provider with mocks
        $this->provider = new BalanceProvider(
            $this->emMock,
            $this->redisCacheMock,
            $security,
            $limiter
        );
    }

    private function mockUser(): User
    {
        $user = $this->createMock(User::class);

        $user->method('getId')
            ->willReturn(Uuid::uuid7());

        $user->method('getEmail')
            ->willReturn('mock@example.com');

        $user->method('getUserIdentifier')
            ->willReturn('mock@example.com');

        $user->method('getRoles')
            ->willReturn(['ROLE_USER']);

        return $user;
    }

    public function testProvideThrowsNotFoundException(): void
    {
        $accountId = 'acct-999';
        $cacheKey = "balance_$accountId";

        // 1. Configure repository to return null (Balance not found)
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['account' => $accountId])
            ->willReturn(null);

        // 2. Configure the cache mock to simulate a cache miss by executing the callback
        $this->redisCacheMock
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->willReturnCallback(
                function ($key, $callback) {
                    // ItemInterface mock inside the callback
                    $itemMock = $this->createMock(ItemInterface::class);
                    $itemMock->expects($this->once())->method('expiresAfter')->with(60);

                    // Execute the provider's logic/callback, which should throw the exception
                    return $callback($itemMock);
                }
            );

        // 3. Assert that NotFoundHttpException is thrown
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage("Balance not found for account: $accountId");


        $this->provider->provide(
            $this->createMock(Operation::class),
            ['id' => $accountId]
        );
    }

    /**
    * Tests that an InvalidArgumentException is thrown when the 'id' URI variable is missing.
    */
    public function testProvideThrowsInvalidArgumentExceptionWhenIdIsMissing(): void
    {
        // Assert that \InvalidArgumentException is thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Account ID is required.');

        // Ensure cache and database are NOT touched
        $this->redisCacheMock->expects($this->never())->method('get');
        $this->repositoryMock->expects($this->never())->method('findOneBy');


        $this->provider->provide(
            $this->createMock(Operation::class),
            [] // Missing 'id'
        );
    }

    /**
     * Tests that a cached result is returned directly, ensuring the database is NOT queried (Cache hit).
     */
    public function testProvideSuccessOnCacheHit(): void
    {
        $accountId = 'acct-456';
        $cachedDto = new BalanceRead(accountId: $accountId, available: "99.99");
        $cacheKey = "balance_$accountId";

        // Configure the cache mock to return the cached DTO directly (simulating cache hit)
        $this->redisCacheMock
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->willReturn($cachedDto);

        // Ensure the repository is NOT called
        $this->repositoryMock->expects($this->never())->method('findOneBy');

        // Act
        $result = $this->provider->provide(
            $this->createMock(Operation::class),
            ['id' => $accountId]
        );

        // Assert
        $this->assertSame($cachedDto, $result);
    }

    /**
     * Tests a successful retrieval when the cache misses, ensuring the database is queried and the result is cached.
     */
    public function testProvideSuccessOnCacheMiss(): void
    {
        $accountId = 'acct-123';
        $availableAmount = "1500.50";
        $cacheKey = "balance_$accountId";

        // Mock the Balance entity returned from the database
        $balanceEntity = $this->createMock(Balance::class);
        $balanceEntity->method('getAvailable')->willReturn($availableAmount);

        // Configure the repository to return the Balance entity (Database call)
        $this->repositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['account' => $accountId])
            ->willReturn($balanceEntity);

        // Configure the cache mock to simulate a cache miss by executing the callback (Caching logic test)
        $this->redisCacheMock
            ->expects($this->once())
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->willReturnCallback(
                function ($key, $callback) {
                    // ItemInterface mock inside the callback to check cache expiration setting
                    $itemMock = $this->createMock(ItemInterface::class);
                    $itemMock->expects($this->once())->method('expiresAfter')->with(60);

                    // Execute the provider's logic/callback to generate the DTO and set the cache item
                    return $callback($itemMock);
                }
            );


        $result = $this->provider->provide(
            $this->createMock(Operation::class),
            ['id' => $accountId]
        );

        // Assert
        $this->assertInstanceOf(BalanceRead::class, $result);
        $this->assertEquals($accountId, $result->accountId);
        $this->assertEquals($availableAmount, $result->available);
    }

}

<?php

declare(strict_types=1);

namespace App\ApiResource\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\DTO\BalanceRead;
use App\Entity\Balance;
use App\Services\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class BalanceProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $redisCache,
        private readonly Security $security,
        private readonly RateLimiterService $limiterService
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?object
    {
        // Get Current User

        /** @var User $user */
        $user = $this->security->getUser();

        //  Rate limiting
        $this->limiterService->consumeTransferLimit($user->getId()->toString());
        $accountId = $uriVariables['id'] ?? null;

        if (!$accountId) {
            throw new \InvalidArgumentException('Account ID is required.');
        }

        $cacheKey = "balance_$accountId";

        return $this->redisCache->get($cacheKey, function (ItemInterface $item) use ($accountId) {

            $item->expiresAfter(60);

            /** @var Balance|null $balance */
            $balance = $this->em
                ->getRepository(Balance::class)
                ->findOneBy(['account' => $accountId]);

            if (!$balance) {
                throw new NotFoundHttpException("Balance not found for account: $accountId");
            }

            return new BalanceRead(
                accountId: $accountId,
                available: $balance->getAvailable()
            );
        });
    }
}

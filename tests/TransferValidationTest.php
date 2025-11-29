<?php

declare(strict_types=1);

namespace App\Tests\Api\Transfer;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;

final class TransferValidationTest extends ApiTestCase
{
    private const ENDPOINT = '/api/transfers';


    #[DataProvider('invalidPayloadProvider')]
    public function testValidationErrors(array $override, string $expectedField): void
    {
        $payload = [...$this->baseValidPayload(), ...$override];

        static::$alwaysBootKernel = true;
        $client = static::createClient();

        $user = new User();
        $user->setEmail('test_api_user');
        $user->setRoles(['ROLE_USER']);
        $client->loginUser($user, 'api');
        $client->request('POST', self::ENDPOINT, [
            'headers' => [

                'Content-Type' => 'application/ld+json',
            ],
            'json' => $payload,
        ]);

        $this->assertResponseStatusCodeSame(422);

        $content = $client->getResponse()->toArray(false);

        $this->assertArrayHasKey('violations', $content);

        $this->assertTrue(
            $this->violationExists($content['violations'], $expectedField),
            sprintf("Expected validation error for field '%s'", $expectedField)
        );
    }

    private function baseValidPayload(): array
    {
        return [
            "fromAccountId"   => "019acb5c-53e7-7057-80ef-c45311e63cf3",
            "toAccountId"     => "019acb5c-53e6-7087-a18c-1a47e3b26ad5",
            "amount"          => "100",
            "currency"        => "INR",
            "idempotencyKey"  => "test-001",
        ];
    }

    private function violationExists(array $violations, string $field): bool
    {
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? '') === $field) {
                return true;
            }
        }
        return false;
    }

    public static function invalidPayloadProvider(): iterable
    {
        yield 'fromAccountId empty' => [['fromAccountId' => ''], 'fromAccountId'];
        yield 'fromAccountId invalid uuid' => [['fromAccountId' => 'abc'], 'fromAccountId'];

        yield 'toAccountId empty' => [['toAccountId' => ''], 'toAccountId'];
        yield 'toAccountId invalid uuid' => [['toAccountId' => '1234-5678'], 'toAccountId'];

        yield 'amount negative' => [['amount' => '-10'], 'amount'];
        yield 'amount too many decimals' => [['amount' => '10.999'], 'amount'];
        yield 'amount invalid string' => [['amount' => 'abc'], 'amount'];
        yield 'amount empty' => [['amount' => ''], 'amount'];

        yield 'currency lowercase' => [['currency' => 'inr'], 'currency'];
        yield 'currency too short' => [['currency' => 'IN'], 'currency'];
        yield 'currency numeric' => [['currency' => '123'], 'currency'];
        yield 'currency empty' => [['currency' => ''], 'currency'];

        yield 'idempotencyKey empty' => [['idempotencyKey' => ''], 'idempotencyKey'];

        yield 'fromAccountId same as toAccountId' => [
        [
        'fromAccountId' => '019acd5c-6600-71ab-9d7f-038663ab5c1f',
        'toAccountId'   => '019acd5c-6600-71ab-9d7f-038663ab5c1f',
        ],
        'toAccountId'
    ];
    }
}

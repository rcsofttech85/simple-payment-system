<?php

declare(strict_types=1);

namespace App\Tests\Api\Transfer;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TransferValidationTest extends ApiTestCase
{
    private const ENDPOINT = '/api/transfers';
    private const JWT_TOKEN  = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3NjQzOTIwNzAsImV4cCI6MTc2NTM5MjA3MCwianRpIjoiZDM5N2YyYTFjYTk4OTk3YzljZDYxOWM1MDdlMTQyMGQiLCJyb2xlcyI6WyJST0xFX1VTRVIiXSwidXNlcm5hbWUiOiJvZXJkbWFuQHlhaG9vLmNvbSJ9.A_witb_HyzXlSvqLefptStQxQBL2AQjtLqdkPcY_o8mTSdP72tYo-VwBVf08nx4ct-zreK3uF1UFW32j95y4JVQeRNmMtK1FJVbxemBExhLQuQyXMr7iGnicHG6J9YTbopmNEOlXT54iXbVS9MzlTtQtbjI-TH61c2ftBXFj_6PYuLJ7kB748DE8uxdUsWcn90pS30FgVqWU2H-rgMCN7Krn7OPjY3hb_LzAJijSCWS3In-BaO6KwNxzCBkAvz7pLJtbZDsd0VKzON5UpcwC11aydM39stPEWtu-QqfSXZ3Hg9mW35Qqh4_y3lxETTTRGURyk12BRBtXCklnZAG8Cw";



    #[DataProvider('invalidPayloadProvider')]

    public function testValidationErrors(array $override, string $expectedField): void
    {
        $payload = [...$this->baseValidPayload(), ...$override];

        static::$alwaysBootKernel = true;
        $client = static::createClient();
        $client->request('POST', self::ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . self::JWT_TOKEN,
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

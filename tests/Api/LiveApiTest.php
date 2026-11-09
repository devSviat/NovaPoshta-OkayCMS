<?php

namespace Modules\Sviat\NovaPoshta\Api;

require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\CurlTransport;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\NpApiException;
use Okay\Modules\Sviat\NovaPoshta\Api\NpErrorKind;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use PHPUnit\Framework\TestCase;

/**
 * Звертається до Нової Пошти; запускається лише із заданим NP_TEST_API_KEY (тестовий ключ, ніколи не ключ магазину).
 */
class LiveApiTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $key = getenv('NP_TEST_API_KEY');
        if (!is_string($key) || $key === '') {
            self::markTestSkipped('NP_TEST_API_KEY is not set');
        }
        $this->key = $key;
    }

    private function oldClient(string $key): OldApiClient
    {
        $none = null;

        return new OldApiClient(new CurlTransport(), F::apiKey($this, $none))->withKey($key);
    }

    public function testNewApiIssuesAToken(): void
    {
        self::assertCount(3, explode('.', JwtProvider::issue(new CurlTransport(), $this->key)));
    }

    public function testOldApiAnswers(): void
    {
        $data = $this->oldClient($this->key)->call('Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1]);

        self::assertNotEmpty($data);
    }

    public function testZeroKeyIsInvalidInBothApis(): void
    {
        $zero = str_repeat('0', 32);
        foreach ([
            static fn() => JwtProvider::issue(new CurlTransport(), $zero),
            fn() => $this->oldClient($zero)->call('Counterparty', 'getCounterparties', ['CounterpartyProperty' => 'Sender', 'Page' => 1]),
        ] as $call) {
            try {
                $call();
                self::fail('expected NpApiException');
            } catch (NpApiException $e) {
                self::assertSame(NpErrorKind::InvalidKey, $e->kind);
            }
        }
    }
}

<?php

namespace Modules\Sviat\NovaPoshta\Backend;

require_once __DIR__ . '/../Support/FakeTransport.php';
require_once __DIR__ . '/../Support/InMemoryState.php';
require_once __DIR__ . '/../Support/ApiFixtures.php';

use Modules\Sviat\NovaPoshta\Support\ApiFixtures as F;
use Modules\Sviat\NovaPoshta\Support\FakeTransport;
use Modules\Sviat\NovaPoshta\Support\InMemoryState;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshta\Api\ApiKey;
use Okay\Modules\Sviat\NovaPoshta\Api\Http\TransportException;
use Okay\Modules\Sviat\NovaPoshta\Api\NewApi\JwtProvider;
use Okay\Modules\Sviat\NovaPoshta\Api\OldApi\OldApiClient;
use Okay\Modules\Sviat\NovaPoshta\Backend\KeySettings;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyAccess;
use Okay\Modules\Sviat\NovaPoshta\KeyCheck\KeyChecker;
use PHPUnit\Framework\TestCase;

class KeySettingsTest extends TestCase
{
    private const int NOW = 1_900_000_000;

    private FakeTransport $http;
    private InMemoryState $state;
    /** @var array<string, mixed> */
    private array $settings = [];

    protected function setUp(): void
    {
        $this->http = new FakeTransport();
        $this->state = new InMemoryState();
    }

    private function keySettings(): KeySettings
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(fn(string $name) => $this->settings[$name] ?? null);
        $settings->method('set')->willReturnCallback(function (string $name, $value) {
            $this->settings[$name] = $value;
        });
        $apiKey = new ApiKey($settings);
        $now = static fn(): int => self::NOW;
        $noSleep = static function (): void {
        };
        $checker = new KeyChecker(
            $this->http,
            new OldApiClient($this->http, $apiKey, $noSleep),
            new JwtProvider($this->http, $this->state, $apiKey, $now),
            $this->state,
            $now,
        );

        return new KeySettings($settings, $apiKey, $checker);
    }

    private function answersOk(): void
    {
        $this->http->json(200, ['jwt' => F::jwt(self::NOW + 3600)])->json(200, ['success' => true, 'data' => [['Ref' => 's']]]);
    }

    private function answersInvalid(): void
    {
        $this->http
            ->json(422, ['errors' => ['errorMessage' => 'API key expired']])
            ->json(200, ['success' => false, 'errors' => ['API key expired'], 'errorCodes' => ['20000201204']]);
    }

    private function savedKey(): mixed
    {
        return $this->settings[ApiKey::SETTING] ?? null;
    }

    public function testWorkingKeyIsSavedNormalizedAndRemembered(): void
    {
        $this->answersOk();
        $submission = $this->keySettings()->submit(' "' . F::KEY . '"' . "\n", false);

        self::assertTrue($submission->saved);
        self::assertSame(F::KEY, $this->savedKey());
        self::assertTrue($submission->result->isUsable());
        self::assertEquals($submission->result, $this->keySettings()->lastResult());
    }

    public function testInvalidKeyDoesNotReplaceTheSavedOne(): void
    {
        $this->settings[ApiKey::SETTING] = F::OTHER_KEY;
        $this->answersInvalid();

        $submission = $this->keySettings()->submit(F::KEY, false);

        self::assertFalse($submission->saved);
        self::assertSame(KeyAccess::InvalidKey, $submission->result->newApi);
        self::assertSame(F::OTHER_KEY, $this->savedKey());
        self::assertNull($this->keySettings()->lastResult(), 'the saved key was not checked, so nothing is remembered for it');
    }

    public function testKeyWithoutNewApiIsSavedWithItsResult(): void
    {
        $this->http->json(401, ['errors' => ['errorMessage' => 'Unauthorized']])->json(200, ['success' => true, 'data' => [1]]);

        $submission = $this->keySettings()->submit(F::KEY, false);

        self::assertTrue($submission->saved);
        self::assertSame(F::KEY, $this->savedKey());
        self::assertSame(KeyAccess::Denied, $this->keySettings()->lastResult()?->newApi);
    }

    public function testUncheckedKeyIsSavedWhenNovaPoshtaIsDown(): void
    {
        $this->http->push(new TransportException('a'), new TransportException('b'), new TransportException('c'));

        $submission = $this->keySettings()->submit(F::KEY, false);

        self::assertTrue($submission->saved);
        self::assertSame(KeyAccess::Unreachable, $submission->result->oldApi);
    }

    public function testEmptyInputChangesNothing(): void
    {
        $this->settings[ApiKey::SETTING] = F::KEY;

        self::assertNull($this->keySettings()->submit("  \n", false));
        self::assertSame(F::KEY, $this->savedKey());
        self::assertCount(0, $this->http->requests());
    }

    public function testRecheckChecksTheSavedKey(): void
    {
        $this->settings[ApiKey::SETTING] = F::KEY;
        $this->answersInvalid();

        $submission = $this->keySettings()->submit('', true);

        self::assertFalse($submission->saved);
        self::assertSame(F::KEY, $this->savedKey(), 'recheck never removes the key');
        self::assertSame(KeyAccess::InvalidKey, $this->keySettings()->lastResult()?->newApi);
        self::assertStringEndsWith('apiKey=' . F::KEY, $this->http->requests()[0]->url);
    }

    public function testRecheckWithoutAKeyDoesNothing(): void
    {
        self::assertNull($this->keySettings()->submit('', true));
        self::assertCount(0, $this->http->requests());
    }
}

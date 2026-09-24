<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Config;

use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\InvalidConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MetaConfigTest extends TestCase
{
    public function testItCreatesAValidConfiguration(): void
    {
        $config = new MetaConfig(
            accessToken: 'valid-access-token',
            adAccountId: 'act_123456789',
            apiVersion: 'v23.0',
        );

        self::assertSame('valid-access-token', $config->accessToken());
        self::assertSame('act_123456789', $config->adAccountId());
        self::assertSame('v23.0', $config->apiVersion());
    }

    public function testItIsImmutable(): void
    {
        $reflection = new ReflectionClass(MetaConfig::class);

        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->getProperty('accessToken')->isPrivate());
        self::assertTrue($reflection->getProperty('adAccountId')->isPrivate());
        self::assertTrue($reflection->getProperty('apiVersion')->isPrivate());
    }

    public function testItPreservesTheAccessTokenExactly(): void
    {
        $config = new MetaConfig(
            accessToken: "  token-with-whitespace\n",
            adAccountId: 'act_123456789',
            apiVersion: 'v24.0',
        );

        self::assertSame("  token-with-whitespace\n", $config->accessToken());
    }

    public function testItNormalizesANumericAdAccountId(): void
    {
        $config = new MetaConfig('token', '00123456789', 'v23.0');

        self::assertSame('act_00123456789', $config->adAccountId());
    }

    #[DataProvider('invalidAccessTokenProvider')]
    public function testItRejectsAnInvalidAccessToken(string $accessToken): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Meta Access Token');

        new MetaConfig($accessToken, 'act_123456789', 'v23.0');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAccessTokenProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces only' => ['   '];
        yield 'whitespace only' => ["\t\n"];
    }

    #[DataProvider('invalidAdAccountIdProvider')]
    public function testItRejectsAnInvalidAdAccountId(string $adAccountId): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Meta Ad Account ID');

        new MetaConfig('token', $adAccountId, 'v23.0');
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAdAccountIdProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'missing numeric ID' => ['act_'];
        yield 'non-numeric ID' => ['act_abc'];
        yield 'missing prefix' => ['abc123'];
        yield 'embedded underscore' => ['act_123_456'];
        yield 'surrounding whitespace' => [' act_123456789 '];
    }

    #[DataProvider('invalidApiVersionProvider')]
    public function testItRejectsAnInvalidApiVersion(string $apiVersion): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Meta Marketing API Version');

        new MetaConfig('token', 'act_123456789', $apiVersion);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidApiVersionProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'number only' => ['23'];
        yield 'missing v prefix' => ['23.0'];
        yield 'long prefix' => ['version23'];
        yield 'missing minor version' => ['v23'];
        yield 'non-numeric minor version' => ['v23.x'];
        yield 'surrounding whitespace' => [' v23.0 '];
    }

    public function testValidationExceptionsNeverExposeTheAccessToken(): void
    {
        $sensitiveToken = 'secret-token-that-must-not-leak';

        try {
            new MetaConfig($sensitiveToken, 'invalid-account', 'v23.0');
            self::fail('Expected invalid configuration to throw.');
        } catch (InvalidConfigurationException $exception) {
            self::assertStringNotContainsString($sensitiveToken, $exception->getMessage());
        }
    }

    public function testDebugOutputRedactsTheAccessToken(): void
    {
        $sensitiveToken = 'secret-token-that-must-not-leak';
        $config = new MetaConfig($sensitiveToken, 'act_123456789', 'v23.0');

        ob_start();
        var_dump($config);
        $debugOutput = (string) ob_get_clean();

        self::assertStringNotContainsString($sensitiveToken, $debugOutput);
        self::assertStringContainsString('[REDACTED]', $debugOutput);
    }

    public function testSerializedOutputRedactsTheAccessToken(): void
    {
        $sensitiveToken = 'secret-token-that-must-not-leak';
        $config = new MetaConfig($sensitiveToken, 'act_123456789', 'v23.0');

        $serialized = serialize($config);

        self::assertStringNotContainsString($sensitiveToken, $serialized);
        self::assertStringContainsString('[REDACTED]', $serialized);
    }
}

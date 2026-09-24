<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Integration\Authentication;

use MetaMetrics\Authentication\AuthenticationService;
use MetaMetrics\Client\MetaClient;
use MetaMetrics\Client\Request;
use MetaMetrics\Config\MetaConfig;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    public function testConfiguredCredentialsCanAccessTheAdAccount(): void
    {
        $credentials = $this->localCredentials();
        $config = new MetaConfig(
            $credentials['accessToken'],
            $credentials['adAccountId'],
            $credentials['apiVersion'],
        );
        $client = new MetaClient($config);

        $result = (new AuthenticationService($client, $config))->validate();

        self::assertTrue($result->authenticated());
        self::assertTrue($result->accountAccessible());
        self::assertSame($config->adAccountId(), $result->accountId());

        $response = $client->send(new Request(
            method: 'GET',
            path: sprintf('/%s/%s', $config->apiVersion(), $config->adAccountId()),
            query: ['fields' => 'id,name,account_id,account_status,currency,timezone_name'],
        ));

        self::assertSame(200, $response->statusCode());
        self::assertSame([
            'id' => 'act_4274485329463051',
            'name' => 'Triizync Solution Ad account',
            'account_id' => '4274485329463051',
            'account_status' => 1,
            'currency' => 'BDT',
            'timezone_name' => 'Europe/London',
        ], $response->body());
    }

    /**
     * @return array{accessToken: string, adAccountId: string, apiVersion: string}
     */
    private function localCredentials(): array
    {
        $path = dirname(__DIR__, 2).'/credentials.local.php';

        if (!is_file($path)) {
            self::markTestSkipped('Create tests/credentials.local.php to run live Meta tests.');
        }

        $credentials = require $path;

        if (!is_array($credentials)) {
            self::fail('tests/credentials.local.php must return a credentials array.');
        }

        if (($credentials['accessToken'] ?? '') === '') {
            self::markTestSkipped('Paste the token into tests/credentials.local.php to run live Meta tests.');
        }

        foreach (['accessToken', 'adAccountId', 'apiVersion'] as $key) {
            if (!isset($credentials[$key]) || !is_string($credentials[$key]) || trim($credentials[$key]) === '') {
                self::fail(sprintf('Missing string credential: %s.', $key));
            }
        }

        /** @var array{accessToken: string, adAccountId: string, apiVersion: string} $credentials */
        return $credentials;
    }
}

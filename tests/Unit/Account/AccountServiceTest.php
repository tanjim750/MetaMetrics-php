<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Account;

use MetaMetrics\Account\AccountNormalizer;
use MetaMetrics\Account\AccountService;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Exception\UnexpectedResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountServiceTest extends TestCase
{
    public function testItRetrievesAndNormalizesTheConfiguredAccount(): void
    {
        $client = new AccountFakeClient(new Response(200, $this->account()));
        $account = (new AccountService($client, $this->config()))->get();

        self::assertSame('act_4274485329463051', $account->id());
        self::assertSame('Triizync Solution Ad account', $account->name());
        self::assertSame('4274485329463051', $account->accountId());
        self::assertSame(1, $account->accountStatus());
        self::assertSame('BDT', $account->currency());
        self::assertSame('Europe/London', $account->timezoneName());
        self::assertSame('/v26.0/act_4274485329463051', $client->request()?->path());
        self::assertSame([
            'fields' => 'id,name,account_id,account_status,currency,timezone_name',
        ], $client->request()?->query());
    }

    public function testItMapsAnInaccessibleAccountWithResourceContext(): void
    {
        $client = new AccountFakeClient(new Response(400, ['error' => [
            'message' => 'Object does not exist',
            'type' => 'GraphMethodException',
            'code' => 100,
            'error_subcode' => 33,
        ]]));

        try {
            (new AccountService($client, $this->config()))->get();
            self::fail('Expected inaccessible account failure.');
        } catch (ResourceNotFoundException $exception) {
            self::assertSame('Ad Account', $exception->resourceType());
            self::assertSame('act_4274485329463051', $exception->resourceId());
        }
    }

    public function testItRejectsMalformedAccountResponses(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        (new AccountService(
            new AccountFakeClient(new Response(200, ['id' => 'act_4274485329463051'])),
            $this->config(),
        ))->get();
    }

    #[DataProvider('mismatchedAccountProvider')]
    public function testItRejectsResponsesForAnotherAccount(array $overrides): void
    {
        $client = new AccountFakeClient(new Response(200, [
            ...$this->account(),
            ...$overrides,
        ]));

        try {
            (new AccountService($client, $this->config()))->get();
            self::fail('Expected mismatched account response.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame(200, $exception->httpStatus());
            self::assertSame('Ad Account', $exception->resourceType());
            self::assertSame('act_4274485329463051', $exception->resourceId());
        }
    }

    /** @return iterable<string, array{array<string, string>}> */
    public static function mismatchedAccountProvider(): iterable
    {
        yield 'canonical ID' => [['id' => 'act_999']];
        yield 'numeric account ID' => [['account_id' => '999']];
    }

    public function testOptionalAccountFieldsMayBeAbsent(): void
    {
        $account = (new AccountNormalizer())->normalize([
            'id' => 'act_1',
            'name' => 'Account',
            'account_id' => '1',
        ]);

        self::assertNull($account->accountStatus());
        self::assertNull($account->currency());
        self::assertNull($account->timezoneName());
    }

    #[DataProvider('malformedAccountProvider')]
    public function testNormalizerRejectsMalformedAccountFields(array $data, string $field): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($field);

        (new AccountNormalizer())->normalize($data);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function malformedAccountProvider(): iterable
    {
        yield 'missing ID' => [['name' => 'Account', 'account_id' => '1'], 'id'];
        yield 'missing name' => [['id' => 'act_1', 'account_id' => '1'], 'name'];
        yield 'missing account ID' => [['id' => 'act_1', 'name' => 'Account'], 'account_id'];
        yield 'invalid status' => [[
            'id' => 'act_1',
            'name' => 'Account',
            'account_id' => '1',
            'account_status' => '1',
        ], 'account_status'];
    }

    /** @return array<string, int|string> */
    private function account(): array
    {
        return [
            'id' => 'act_4274485329463051',
            'name' => 'Triizync Solution Ad account',
            'account_id' => '4274485329463051',
            'account_status' => 1,
            'currency' => 'BDT',
            'timezone_name' => 'Europe/London',
        ];
    }

    private function config(): MetaConfig
    {
        return new MetaConfig('test-token', 'act_4274485329463051', 'v26.0');
    }
}

final class AccountFakeClient implements MetaClientInterface
{
    private ?Request $request = null;

    public function __construct(private readonly Response $response)
    {
    }

    public function send(Request $request): Response
    {
        $this->request = $request;

        return $this->response;
    }

    public function request(): ?Request
    {
        return $this->request;
    }
}

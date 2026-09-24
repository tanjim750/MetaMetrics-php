<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Authentication;

use Closure;
use MetaMetrics\Authentication\AuthenticationService;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Config\MetaConfig;
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\NetworkException;
use MetaMetrics\Exception\PermissionException;
use MetaMetrics\Exception\RateLimitException;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Exception\UnexpectedResponseException;
use PHPUnit\Framework\TestCase;

final class AuthenticationServiceTest extends TestCase
{
    private const TOKEN = 'known-secret-test-token';

    public function testItValidatesAuthenticationAndAccountAccess(): void
    {
        $client = FakeMetaClient::returning(new Response(200, ['id' => 'act_123456789']));
        $service = new AuthenticationService($client, $this->config());

        $result = $service->validate();

        self::assertTrue($result->authenticated());
        self::assertTrue($result->accountAccessible());
        self::assertSame('act_123456789', $result->accountId());

        $request = $client->lastRequest();
        self::assertSame('GET', $request->method());
        self::assertSame('/v23.0/act_123456789', $request->path());
        self::assertSame(['fields' => 'id'], $request->query());
    }

    public function testItMapsAnInvalidTokenToAuthenticationException(): void
    {
        $response = $this->errorResponse(400, 190, null, 'OAuthException');

        $exception = $this->captureException($response, AuthenticationException::class);

        self::assertSame(190, $exception->metaErrorCode());
        self::assertSame('OAuthException', $exception->metaErrorType());
    }

    public function testItMapsAnExpiredTokenToAuthenticationException(): void
    {
        $response = $this->errorResponse(400, 190, 463, 'OAuthException', 'trace-expired');

        $exception = $this->captureException($response, AuthenticationException::class);

        self::assertSame(190, $exception->metaErrorCode());
        self::assertSame(463, $exception->metaErrorSubcode());
        self::assertSame('trace-expired', $exception->traceId());
    }

    public function testItMapsMissingPermissionToPermissionException(): void
    {
        $response = $this->errorResponse(403, 200, null, 'OAuthException');

        $exception = $this->captureException($response, PermissionException::class);

        self::assertSame(200, $exception->metaErrorCode());
    }

    public function testItDoesNotClassifyAnInaccessibleAccountAsAnInvalidToken(): void
    {
        $response = $this->errorResponse(400, 100, 33, 'GraphMethodException');

        $exception = $this->captureException($response, ResourceNotFoundException::class);

        self::assertSame(100, $exception->metaErrorCode());
        self::assertSame(33, $exception->metaErrorSubcode());
    }

    public function testItPreservesNetworkExceptionsFromTheClient(): void
    {
        $networkException = new NetworkException('Unable to connect to Meta.');
        $client = new FakeMetaClient(static function () use ($networkException): never {
            throw $networkException;
        });

        $this->expectExceptionObject($networkException);

        (new AuthenticationService($client, $this->config()))->validate();
    }

    public function testItMapsRateLimitErrorsSeparately(): void
    {
        $response = $this->errorResponse(400, 4, null, 'OAuthException', 'trace-rate');

        $exception = $this->captureException($response, RateLimitException::class);

        self::assertSame(4, $exception->metaErrorCode());
        self::assertSame('trace-rate', $exception->traceId());
    }

    public function testItMapsHttpRateLimitWithoutAnErrorPayload(): void
    {
        $exception = $this->captureException(new Response(429, []), RateLimitException::class);

        self::assertNull($exception->metaErrorCode());
    }

    public function testItRejectsASuccessResponseWithTheWrongAccount(): void
    {
        $client = FakeMetaClient::returning(new Response(200, ['id' => 'act_987654321']));

        $this->expectException(UnexpectedResponseException::class);

        (new AuthenticationService($client, $this->config()))->validate();
    }

    public function testItRejectsAMalformedSuccessResponse(): void
    {
        $client = FakeMetaClient::returning(new Response(200, '<html>not json</html>'));

        $this->expectException(UnexpectedResponseException::class);

        (new AuthenticationService($client, $this->config()))->validate();
    }

    public function testCredentialsNeverAppearInResultsRequestsOrExceptions(): void
    {
        $client = FakeMetaClient::returning(new Response(200, ['id' => 'act_123456789']));
        $result = (new AuthenticationService($client, $this->config()))->validate();

        self::assertStringNotContainsString(self::TOKEN, serialize($result));
        self::assertStringNotContainsString(self::TOKEN, serialize($client->lastRequest()));

        $response = new Response(400, [
            'error' => [
                'message' => 'Rejected token '.self::TOKEN,
                'type' => 'OAuthException',
                'code' => 190,
            ],
        ]);
        $exception = $this->captureException($response, AuthenticationException::class);

        self::assertStringNotContainsString(self::TOKEN, $exception->getMessage());
        self::assertStringNotContainsString(self::TOKEN, serialize($exception));
    }

    private function config(): MetaConfig
    {
        return new MetaConfig(self::TOKEN, 'act_123456789', 'v23.0');
    }

    private function errorResponse(
        int $statusCode,
        int $code,
        ?int $subcode,
        string $type,
        string $traceId = 'trace-id',
    ): Response {
        return new Response($statusCode, [
            'error' => array_filter([
                'message' => 'Meta supplied message',
                'type' => $type,
                'code' => $code,
                'error_subcode' => $subcode,
                'fbtrace_id' => $traceId,
            ], static fn (mixed $value): bool => $value !== null),
        ]);
    }

    /**
     * @template T of \Throwable
     * @param class-string<T> $expectedClass
     * @return T
     */
    private function captureException(Response $response, string $expectedClass): \Throwable
    {
        $client = FakeMetaClient::returning($response);

        try {
            (new AuthenticationService($client, $this->config()))->validate();
            self::fail(sprintf('Expected %s to be thrown.', $expectedClass));
        } catch (\Throwable $exception) {
            self::assertInstanceOf($expectedClass, $exception);

            return $exception;
        }
    }
}

final class FakeMetaClient implements MetaClientInterface
{
    private ?Request $lastRequest = null;

    /** @param Closure(Request): Response $handler */
    public function __construct(private readonly Closure $handler)
    {
    }

    public static function returning(Response $response): self
    {
        return new self(static fn (): Response => $response);
    }

    public function send(Request $request): Response
    {
        $this->lastRequest = $request;

        return ($this->handler)($request);
    }

    public function lastRequest(): Request
    {
        if ($this->lastRequest === null) {
            throw new \LogicException('No request has been sent.');
        }

        return $this->lastRequest;
    }
}

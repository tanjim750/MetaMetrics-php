<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Authentication;

use JsonException;
use MetaMetrics\Authentication\MetaErrorMapper;
use MetaMetrics\Authentication\MetaErrorParser;
use MetaMetrics\Client\JsonResponseDecoder;
use MetaMetrics\Client\MetaClientInterface;
use MetaMetrics\Client\Pagination\Paginator;
use MetaMetrics\Client\Request;
use MetaMetrics\Client\Response;
use MetaMetrics\Exception\AuthenticationException;
use MetaMetrics\Exception\InvalidConfigurationException;
use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\MetaAdsException;
use MetaMetrics\Exception\NetworkException;
use MetaMetrics\Exception\PermissionException;
use MetaMetrics\Exception\RateLimitException;
use MetaMetrics\Exception\ResourceNotFoundException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Exception\UnsupportedMetricException;
use MetaMetrics\Logging\NullLogger;
use MetaMetrics\Support\DiagnosticSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ErrorHandlingTest extends TestCase
{
    /** @param class-string<MetaAdsException> $exceptionClass */
    #[DataProvider('exceptionClassProvider')]
    public function testEverySpecificExceptionExtendsTheRoot(string $exceptionClass): void
    {
        self::assertTrue(is_subclass_of($exceptionClass, MetaAdsException::class));
    }

    /** @return iterable<string, array{class-string<MetaAdsException>}> */
    public static function exceptionClassProvider(): iterable
    {
        yield AuthenticationException::class => [AuthenticationException::class];
        yield PermissionException::class => [PermissionException::class];
        yield RateLimitException::class => [RateLimitException::class];
        yield NetworkException::class => [NetworkException::class];
        yield InvalidConfigurationException::class => [InvalidConfigurationException::class];
        yield InvalidInputException::class => [InvalidInputException::class];
        yield ResourceNotFoundException::class => [ResourceNotFoundException::class];
        yield UnsupportedMetricException::class => [UnsupportedMetricException::class];
        yield UnexpectedResponseException::class => [UnexpectedResponseException::class];
    }

    public function testRateLimitPreservesSafeRetryMetadataWithoutRetrying(): void
    {
        $response = new Response(400, ['error' => [
            'message' => 'Application request limit reached',
            'type' => 'OAuthException',
            'code' => 4,
            'error_subcode' => 99,
            'fbtrace_id' => 'trace-rate',
        ]], headers: [
            'Retry-After' => '120',
            'X-App-Usage' => '{"call_count":100}',
            'Authorization' => 'Bearer secret',
        ]);

        $exception = (new MetaErrorMapper())->map($response);

        self::assertInstanceOf(RateLimitException::class, $exception);
        self::assertTrue($exception->isRetryable());
        self::assertSame(120, $exception->retryAfterSeconds());
        self::assertSame(400, $exception->httpStatus());
        self::assertSame('trace-rate', $exception->traceId());
        self::assertSame('{"call_count":100}', $exception->context()['x_app_usage']);
        self::assertStringNotContainsString('secret', serialize($exception));
    }

    public function testUnknownMetaErrorUsesGenericRootException(): void
    {
        $exception = (new MetaErrorMapper())->map(new Response(400, ['error' => [
            'message' => 'Unknown failure',
            'type' => 'GraphAPIException',
            'code' => 987654,
            'error_subcode' => 123,
            'fbtrace_id' => 'trace-unknown',
        ]]));

        self::assertSame(MetaAdsException::class, $exception::class);
        self::assertSame(987654, $exception->metaErrorCode());
        self::assertSame(400, $exception->httpStatus());
        self::assertFalse($exception->isRetryable());
    }

    public function testServerFailureIsGenericAndRetryable(): void
    {
        $exception = (new MetaErrorMapper())->map(new Response(503, [
            'error' => ['message' => 'Unavailable', 'type' => 'GraphAPIException', 'code' => 2],
        ]));

        self::assertSame(MetaAdsException::class, $exception::class);
        self::assertTrue($exception->isRetryable());
        self::assertSame(503, $exception->httpStatus());
    }

    public function testMetaTransientIndicatorMakesGenericFailureRetryable(): void
    {
        $exception = (new MetaErrorMapper())->map(new Response(400, [
            'error' => [
                'message' => 'Temporary failure',
                'type' => 'GraphAPIException',
                'code' => 999999,
                'is_transient' => true,
            ],
        ]));

        self::assertSame(MetaAdsException::class, $exception::class);
        self::assertTrue($exception->isRetryable());
    }

    /** @param class-string<MetaAdsException> $expected */
    #[DataProvider('sameHttpStatusProvider')]
    public function testHttpStatusIsNotTheSoleClassifier(int $code, ?int $subcode, string $type, string $expected): void
    {
        $exception = (new MetaErrorMapper())->map(new Response(400, ['error' => array_filter([
            'message' => 'Meta failure',
            'type' => $type,
            'code' => $code,
            'error_subcode' => $subcode,
        ], static fn (mixed $value): bool => $value !== null)]));

        self::assertInstanceOf($expected, $exception);
    }

    /** @return iterable<string, array{int, int|null, string, class-string<MetaAdsException>}> */
    public static function sameHttpStatusProvider(): iterable
    {
        yield 'authentication' => [190, 463, 'OAuthException', AuthenticationException::class];
        yield 'permission' => [200, null, 'OAuthException', PermissionException::class];
        yield 'rate limit' => [4, null, 'OAuthException', RateLimitException::class];
        yield 'resource' => [100, 33, 'GraphMethodException', ResourceNotFoundException::class];
        yield 'generic OAuth error without authentication evidence' => [
            100,
            null,
            'OAuthException',
            MetaAdsException::class,
        ];
    }

    public function testResourceContextIsPreservedSafely(): void
    {
        $exception = (new MetaErrorMapper())->map(
            new Response(400, ['error' => [
                'message' => 'Object does not exist',
                'type' => 'GraphMethodException',
                'code' => 100,
                'error_subcode' => 33,
            ]]),
            'Campaign',
            'campaign-123',
        );

        self::assertInstanceOf(ResourceNotFoundException::class, $exception);
        self::assertSame('Campaign', $exception->resourceType());
        self::assertSame('campaign-123', $exception->resourceId());
        self::assertSame('campaign-123', $exception->context()['resource_id']);
    }

    public function testMalformedErrorEnvelopeIsUnexpectedResponse(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        (new MetaErrorMapper())->map(new Response(400, ['error' => 'invalid']));
    }

    public function testMalformedErrorDiagnosticsRetainHttpStatus(): void
    {
        try {
            (new MetaErrorMapper())->map(new Response(502, ['error' => [
                'message' => 'Failure',
                'code' => 'not-an-integer',
            ]]));
            self::fail('Expected malformed diagnostics to throw.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame(502, $exception->httpStatus());
        }
    }

    public function testMalformedJsonIsWrappedAndPreservesItsCause(): void
    {
        try {
            (new JsonResponseDecoder())->decode('{invalid', 502);
            self::fail('Expected malformed JSON to throw.');
        } catch (UnexpectedResponseException $exception) {
            self::assertInstanceOf(JsonException::class, $exception->getPrevious());
            self::assertSame(502, $exception->httpStatus());
            self::assertStringNotContainsString('{invalid', $exception->getMessage());
        }
    }

    public function testTraceHeaderIsUsedWhenTheErrorPayloadHasNoTraceId(): void
    {
        $exception = (new MetaErrorMapper())->map(new Response(
            400,
            ['error' => ['message' => 'Unknown failure', 'code' => 987654]],
            headers: ['X-FB-Trace-ID' => 'trace-from-header'],
        ));

        self::assertSame('trace-from-header', $exception->traceId());
    }

    public function testErrorEnvelopeIsMappedEvenWithSuccessfulHttpStatus(): void
    {
        $response = new Response(200, ['error' => [
            'message' => 'Invalid token',
            'type' => 'OAuthException',
            'code' => 190,
        ]]);

        self::assertFalse($response->isSuccessful());

        try {
            (new Paginator(new ErrorSequenceClient($response)))
                ->fetchAll(new Request('GET', '/v26.0/items'));
            self::fail('Expected the Meta error envelope to be mapped.');
        } catch (AuthenticationException $exception) {
            self::assertSame(190, $exception->metaErrorCode());
        }
    }

    public function testDiagnosticSanitizationRedactsUrlsMessagesAndHeaders(): void
    {
        $sanitizer = new DiagnosticSanitizer();
        $secret = 'EAAB_SUPER_SECRET_TEST_TOKEN';
        $secretProof = 'SUPER_SECRET_APP_PROOF';
        $url = $sanitizer->sanitizeUrl(
            'https://graph.facebook.com/v26.0/me?fields=id&ACCESS_TOKEN='.$secret.'&appsecret_proof='.$secretProof,
        );
        $message = $sanitizer->sanitizeMessage(
            'Failed access_token='.$secret
            .' payload={"app_secret":"'.$secretProof.'"}'
            .' encoded=appsecret_proof%3D'.$secretProof
            .' Authorization: Bearer '.$secret,
        );
        $headers = $sanitizer->sanitizeHeaders([
            'Authorization' => 'Bearer '.$secret,
            'Retry-After' => '30',
        ]);

        self::assertStringNotContainsString($secret, $url);
        self::assertStringNotContainsString($secretProof, $url);
        self::assertStringNotContainsString($secret, $message);
        self::assertStringNotContainsString($secretProof, $message);
        self::assertSame(['retry-after' => '30'], $headers);
    }

    public function testParsedUpstreamMessageIsSanitized(): void
    {
        $secret = 'EAAB_SUPER_SECRET_TEST_TOKEN';
        $error = (new MetaErrorParser())->parse(new Response(400, ['error' => [
            'message' => 'Rejected access_token='.$secret,
            'code' => 190,
        ]]));

        self::assertNotNull($error->message());
        self::assertStringNotContainsString($secret, $error->message());
    }

    public function testNetworkExceptionCanPreserveTheTransportCause(): void
    {
        $cause = new RuntimeException('Transport failed.');
        $exception = new NetworkException(
            'Meta API network request failed.',
            retryable: true,
            previous: $cause,
        );

        self::assertSame($cause, $exception->getPrevious());
        self::assertTrue($exception->isRetryable());
    }

    public function testMidPaginationRateLimitDoesNotReturnPartialData(): void
    {
        $client = new ErrorSequenceClient(
            new Response(200, [
                'data' => [['id' => 'first']],
                'paging' => [
                    'next' => 'https://graph.facebook.com/next',
                    'cursors' => ['after' => 'next-page'],
                ],
            ]),
            new Response(429, ['error' => ['message' => 'Throttled', 'code' => 4]]),
        );

        $this->expectException(RateLimitException::class);

        (new Paginator($client))->fetchAll(new Request('GET', '/v26.0/items'));
    }

    public function testNullLoggerHasNoSideEffects(): void
    {
        (new NullLogger())->error('Operation failed.', ['retryable' => false]);

        self::assertTrue(true);
    }
}

final class ErrorSequenceClient implements MetaClientInterface
{
    /** @var list<Response|Throwable> */
    private array $responses;

    public function __construct(Response|Throwable ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(Request $request): Response
    {
        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        if (!$response instanceof Response) {
            throw new RuntimeException('No fake response remains.');
        }

        return $response;
    }
}

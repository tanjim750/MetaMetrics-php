<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Tracking;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Tracking\TrackingIdentifiers;
use MetaMetrics\Tracking\TrackingUrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrackingUrlParserTest extends TestCase
{
    private TrackingUrlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new TrackingUrlParser();
    }

    public function testItParsesACompleteTrackingUrl(): void
    {
        $result = $this->parser->parse(
            'https://example.com/product/item?utm_campaign=23859320180340794'
            .'&utm_content=23859320180330794&utm_id=23859320180340794'
            .'&utm_medium=paid&utm_source=fb&utm_term=23859320180350794',
        );

        self::assertSame('23859320180340794', $result->campaignId());
        self::assertSame('23859320180350794', $result->adSetId());
        self::assertSame('23859320180330794', $result->adId());
        self::assertSame('fb', $result->source());
        self::assertSame('paid', $result->medium());
    }

    public function testItReturnsNullableFieldsForAPartialUrl(): void
    {
        $result = $this->parser->parse(
            'https://example.com/product?utm_content=23859320180330794',
        );

        self::assertNull($result->campaignId());
        self::assertNull($result->adSetId());
        self::assertSame('23859320180330794', $result->adId());
        self::assertNull($result->source());
        self::assertNull($result->medium());
    }

    public function testNumericUtmCampaignIsUsedAsCampaignFallback(): void
    {
        $result = $this->parser->parse(
            'https://example.com/product?utm_campaign=23859320180340794',
        );

        self::assertSame('23859320180340794', $result->campaignId());
    }

    public function testCampaignNameIsNotUsedAsCampaignId(): void
    {
        $result = $this->parser->parse(
            'https://example.com/product?utm_campaign=summer_sale',
        );

        self::assertNull($result->campaignId());
    }

    public function testPrimaryCampaignIdWinsOverFallback(): void
    {
        $result = $this->parser->parse(
            'https://example.com/product?utm_id=111&utm_campaign=222',
        );

        self::assertSame('111', $result->campaignId());
    }

    public function testItSupportsPartialCustomParameterMapping(): void
    {
        $result = $this->parser->parse(
            'https://example.com/product?campaign_id=111&adset_id=222&ad_id=333'
            .'&utm_source=instagram&utm_medium=paid-social',
            [
                'campaignId' => 'campaign_id',
                'adSetId' => 'adset_id',
                'adId' => 'ad_id',
            ],
        );

        self::assertSame('111', $result->campaignId());
        self::assertSame('222', $result->adSetId());
        self::assertSame('333', $result->adId());
        self::assertSame('instagram', $result->source());
        self::assertSame('paid-social', $result->medium());
    }

    public function testFbclidDoesNotProduceEntityIdentifiers(): void
    {
        $result = $this->parser->parse('https://example.com/product?fbclid=opaque-click-id');

        self::assertNull($result->campaignId());
        self::assertNull($result->adSetId());
        self::assertNull($result->adId());
    }

    public function testUrlWithoutTrackingReturnsAnEmptyValueObject(): void
    {
        $result = $this->parser->parse('https://example.com/product?color=blue');

        self::assertInstanceOf(TrackingIdentifiers::class, $result);
        self::assertNull($result->campaignId());
        self::assertNull($result->adSetId());
        self::assertNull($result->adId());
        self::assertNull($result->source());
        self::assertNull($result->medium());
    }

    public function testUrlWithoutQueryReturnsAnEmptyValueObject(): void
    {
        $result = $this->parser->parse('https://example.com/product');

        self::assertInstanceOf(TrackingIdentifiers::class, $result);
        self::assertNull($result->campaignId());
        self::assertNull($result->adSetId());
        self::assertNull($result->adId());
        self::assertNull($result->source());
        self::assertNull($result->medium());
    }

    public function testItPreservesLargeIdsAsStrings(): void
    {
        $id = '999999999999999999999999999999999999';
        $result = $this->parser->parse('https://example.com/?utm_id='.$id);

        self::assertSame($id, $result->campaignId());
        self::assertIsString($result->campaignId());
    }

    public function testItDecodesValuesAndUsesTheLastDuplicate(): void
    {
        $result = $this->parser->parse(
            'https://example.com/?utm_source=first&utm_source=facebook%20ads&utm_medium=',
        );

        self::assertSame('facebook ads', $result->source());
        self::assertNull($result->medium());
    }

    public function testArrayAndEmptyTrackingValuesAreIgnored(): void
    {
        $result = $this->parser->parse(
            'https://example.com/?utm_id[]=111&utm_term=%20%20&utm_content=',
        );

        self::assertNull($result->campaignId());
        self::assertNull($result->adSetId());
        self::assertNull($result->adId());
    }

    #[DataProvider('invalidUrlProvider')]
    public function testItRejectsMalformedUrls(string $url): void
    {
        $this->expectException(InvalidInputException::class);

        $this->parser->parse($url);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidUrlProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'relative path' => ['/product?utm_id=111'];
        yield 'missing host' => ['https:///product?utm_id=111'];
        yield 'invalid host' => ['https://exa mple.com/product?utm_id=111'];
        yield 'unsupported scheme' => ['ftp://example.com/product?utm_id=111'];
    }

    #[DataProvider('invalidMappingProvider')]
    public function testItRejectsInvalidParameterMappings(array $mapping): void
    {
        $this->expectException(InvalidInputException::class);

        $this->parser->parse('https://example.com/', $mapping);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalidMappingProvider(): iterable
    {
        yield 'unknown identifier' => [['pixelId' => 'pixel_id']];
        yield 'empty parameter' => [['campaignId' => '']];
        yield 'invalid parameter characters' => [['campaignId' => 'campaign.id']];
        yield 'non-string parameter' => [['campaignId' => 123]];
        yield 'duplicate parameter' => [['campaignId' => 'utm_term']];
    }
}

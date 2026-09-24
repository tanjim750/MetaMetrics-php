<?php

declare(strict_types=1);

namespace MetaMetrics\Tests\Unit\Insights;

use MetaMetrics\Exception\InvalidInputException;
use MetaMetrics\Exception\UnexpectedResponseException;
use MetaMetrics\Insights\Metric\PurchaseActionMapping;
use MetaMetrics\Insights\Parser\ActionParser;
use MetaMetrics\Insights\Parser\ActionValueParser;
use PHPUnit\Framework\TestCase;

final class PurchaseActionMappingTest extends TestCase
{
    public function testOneMappingCoordinatesCountAndValuePriority(): void
    {
        $mapping = new PurchaseActionMapping([
            'offsite_conversion.fb_pixel_purchase',
            'omni_purchase',
        ]);
        $actions = new ActionParser($mapping);
        $values = new ActionValueParser($mapping);
        $actionRows = [
            ['action_type' => 'omni_purchase', 'value' => '9'],
            ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '7'],
        ];
        $valueRows = [
            ['action_type' => 'omni_purchase', 'value' => '900'],
            ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '700'],
        ];

        $type = $actions->commonPurchaseActionType($actionRows, $valueRows);

        self::assertSame('offsite_conversion.fb_pixel_purchase', $type);
        self::assertSame(7.0, $actions->purchasesForType($actionRows, $type));
        self::assertSame(700.0, $values->purchaseValueForType($valueRows, $type));
    }

    public function testCountAndValueExtractionDoNotDependOnArrayOrder(): void
    {
        $mapping = new PurchaseActionMapping(['purchase']);

        self::assertSame(3.0, (new ActionParser($mapping))->purchases([
            ['action_type' => 'link_click', 'value' => '20'],
            ['action_type' => 'purchase', 'value' => '3'],
        ]));
        self::assertSame(150.0, (new ActionValueParser($mapping))->purchaseValue([
            ['action_type' => 'purchase', 'value' => '150'],
            ['action_type' => 'link_click', 'value' => '20'],
        ]));
    }

    public function testExplicitExtractionCannotBypassConfiguredMapping(): void
    {
        $this->expectException(InvalidInputException::class);

        (new ActionParser(['purchase']))->purchasesForType([
            ['action_type' => 'lead', 'value' => '5'],
        ], 'lead');
    }

    public function testMappingRejectsDuplicates(): void
    {
        $this->expectException(InvalidInputException::class);

        new PurchaseActionMapping(['purchase', 'purchase']);
    }

    public function testDuplicateMetaActionRowsAreRejectedInsteadOfOverwritten(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('duplicate action type');

        (new ActionParser())->purchases([
            ['action_type' => 'purchase', 'value' => '1'],
            ['action_type' => 'purchase', 'value' => '2'],
        ]);
    }

    public function testDuplicateMetaActionValueRowsAreRejectedInsteadOfOverwritten(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('duplicate action type');

        (new ActionValueParser())->purchaseValue([
            ['action_type' => 'purchase', 'value' => '10'],
            ['action_type' => 'purchase', 'value' => '20'],
        ]);
    }

    public function testEmptyActionTypeIsRejected(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('malformed action');

        (new ActionParser())->purchases([
            ['action_type' => '', 'value' => '1'],
        ]);
    }
}

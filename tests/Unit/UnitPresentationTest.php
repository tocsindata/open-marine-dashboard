<?php

namespace Tests\Unit;

use App\Support\UnitPresentation;
use PHPUnit\Framework\TestCase;

class UnitPresentationTest extends TestCase
{
    public function test_metric_reference_length_preserves_two_decimal_places(): void
    {
        $this->assertSame('12.50 m', UnitPresentation::referenceLength('12.50', 'metric'));
        $this->assertSame('0.10 m', UnitPresentation::referenceLength('0.10', 'metric'));
    }

    public function test_imperial_reference_length_uses_the_international_foot_and_retains_metres(): void
    {
        $this->assertSame('≈ 41.01 ft (12.50 m)', UnitPresentation::referenceLength('12.50', 'imperial'));
        $this->assertSame('≈ 0.33 ft (0.10 m)', UnitPresentation::referenceLength('0.10', 'imperial'));
    }

    public function test_unknown_preference_fails_safe_to_metric(): void
    {
        $this->assertSame('12.50 m', UnitPresentation::referenceLength('12.50', 'unsupported'));
        $this->assertSame('12.50 m', UnitPresentation::referenceLength('12.50', null));
    }

    public function test_missing_reference_length_remains_missing(): void
    {
        $this->assertNull(UnitPresentation::referenceLength(null, 'imperial'));
        $this->assertNull(UnitPresentation::referenceLength('', 'imperial'));
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

/**
 * Tests for Carbon 3 compatibility.
 */
class CarbonTest extends TestCase
{
    public function testCarbonExtendsImmutable(): void
    {
        $carbon = Carbon::now();
        $this->assertInstanceOf(Carbon::class, $carbon);
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $carbon);
    }

    public function testCarbonImplementsInterface(): void
    {
        $carbon = Carbon::now();
        $this->assertInstanceOf(CarbonInterface::class, $carbon);
    }

    public function testCarbonNow(): void
    {
        $carbon = Carbon::now();
        $this->assertNotNull($carbon);
        $this->assertInstanceOf(Carbon::class, $carbon);
    }

    public function testCarbonParse(): void
    {
        $carbon = Carbon::parse('2025-01-15 10:30:00');
        $this->assertEquals(2025, $carbon->year);
        $this->assertEquals(1, $carbon->month);
        $this->assertEquals(15, $carbon->day);
        $this->assertEquals(10, $carbon->hour);
        $this->assertEquals(30, $carbon->minute);
    }

    public function testCarbonToday(): void
    {
        $today = Carbon::today();
        $now = Carbon::now();
        $this->assertEquals($today->year, $now->year);
        $this->assertEquals($today->month, $now->month);
        $this->assertEquals($today->day, $now->day);
    }

    public function testCarbonAddDays(): void
    {
        $carbon = Carbon::parse('2025-01-15');
        $future = $carbon->add(5, 'days');
        $this->assertEquals(20, $future->day);
    }

    public function testCarbonSubDays(): void
    {
        $carbon = Carbon::parse('2025-01-15');
        $past = $carbon->sub(5, 'days');
        $this->assertEquals(10, $past->day);
    }

    public function testCarbonDiff(): void
    {
        // Test basic diff without human-readable format (which requires translation setup)
        $earlier = Carbon::parse('2025-01-10 10:00:00');
        $later = Carbon::parse('2025-01-10 10:05:00');
        $diff = $earlier->diffInMinutes($later);
        $this->assertEquals(5, $diff);
    }

    public function testCarbonFormat(): void
    {
        $carbon = Carbon::parse('2025-01-15 10:30:00');
        $this->assertEquals('2025-01-15', $carbon->format('Y-m-d'));
        $this->assertEquals('10:30:00', $carbon->format('H:i:s'));
    }

    public function testCarbonTimezone(): void
    {
        $carbon = Carbon::now('UTC');
        $this->assertEquals('UTC', $carbon->timezone->getName());
    }

    public function testCarbonJsonSerialize(): void
    {
        $carbon = Carbon::parse('2025-01-15 10:30:00');
        $json = json_encode($carbon);
        $this->assertNotFalse($json);
        $this->assertIsString($json);
    }

    public function testCarbonComparison(): void
    {
        $earlier = Carbon::parse('2025-01-10');
        $later = Carbon::parse('2025-01-15');

        $this->assertTrue($earlier->lt($later));
        $this->assertTrue($later->gt($earlier));
        $this->assertFalse($earlier->eq($later));
    }

    public function testCarbonStartOfDay(): void
    {
        $carbon = Carbon::parse('2025-01-15 10:30:00');
        $start = $carbon->startOfDay();
        $this->assertEquals(0, $start->hour);
        $this->assertEquals(0, $start->minute);
        $this->assertEquals(0, $start->second);
    }

    public function testCarbonEndOfDay(): void
    {
        $carbon = Carbon::parse('2025-01-15 10:30:00');
        $end = $carbon->endOfDay();
        $this->assertEquals(23, $end->hour);
        $this->assertEquals(59, $end->minute);
        $this->assertEquals(59, $end->second);
    }

    public function testCarbonCarbonClassExists(): void
    {
        // Carbon\Carbon should exist as an alias to CarbonImmutable
        $this->assertTrue(class_exists(\Carbon\Carbon::class));
    }

    public function testCarbonCarbonInstanceOf(): void
    {
        $carbon = new \Carbon\Carbon();
        // Carbon\Carbon extends CarbonImmutable which implements CarbonInterface
        $this->assertInstanceOf(\Carbon\CarbonImmutable::class, $carbon);
    }

    public function testCarbonMicrosecondsConstant(): void
    {
        // Test that the compatibility constant is available
        $this->assertEquals(1000, \Carbon\Carbon::MICROSECONDS_PER_MILLISECOND);
    }

    public function testCarbonDaysPerWeekConstant(): void
    {
        $this->assertEquals(7, \Carbon\Carbon::DAYS_PER_WEEK);
    }

    public function testCarbonHoursPerDayConstant(): void
    {
        $this->assertEquals(24, \Carbon\Carbon::HOURS_PER_DAY);
    }

    public function testCarbonMinutesPerHourConstant(): void
    {
        $this->assertEquals(60, \Carbon\Carbon::MINUTES_PER_HOUR);
    }

    public function testCarbonSecondsPerMinuteConstant(): void
    {
        $this->assertEquals(60, \Carbon\Carbon::SECONDS_PER_MINUTE);
    }
}

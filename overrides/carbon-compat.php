<?php

/**
 * Carbon 3 compatibility shim.
 *
 * Carbon 3 removed the Carbon\Carbon class in favor of CarbonImmutable.
 * This file creates a compatibility alias for code that still references Carbon\Carbon.
 */

namespace Carbon;

if (!class_exists('Carbon\\Carbon', false)) {
    class Carbon extends CarbonImmutable
    {
        // Re-declare constants from CarbonInterface for static access via Carbon::
        public const YEARS_PER_MILLENNIUM = CarbonInterface::YEARS_PER_MILLENNIUM;
        public const YEARS_PER_CENTURY = CarbonInterface::YEARS_PER_CENTURY;
        public const YEARS_PER_DECADE = CarbonInterface::YEARS_PER_DECADE;
        public const MONTHS_PER_YEAR = CarbonInterface::MONTHS_PER_YEAR;
        public const MONTHS_PER_QUARTER = CarbonInterface::MONTHS_PER_QUARTER;
        public const QUARTERS_PER_YEAR = CarbonInterface::QUARTERS_PER_YEAR;
        public const WEEKS_PER_YEAR = CarbonInterface::WEEKS_PER_YEAR;
        public const WEEKS_PER_MONTH = CarbonInterface::WEEKS_PER_MONTH;
        public const DAYS_PER_YEAR = CarbonInterface::DAYS_PER_YEAR;
        public const DAYS_PER_WEEK = CarbonInterface::DAYS_PER_WEEK;
        public const HOURS_PER_DAY = CarbonInterface::HOURS_PER_DAY;
        public const MINUTES_PER_HOUR = CarbonInterface::MINUTES_PER_HOUR;
        public const SECONDS_PER_MINUTE = CarbonInterface::SECONDS_PER_MINUTE;
        public const MILLISECONDS_PER_SECOND = CarbonInterface::MILLISECONDS_PER_SECOND;
        public const MICROSECONDS_PER_MILLISECOND = CarbonInterface::MICROSECONDS_PER_MILLISECOND;
        public const MICROSECONDS_PER_SECOND = CarbonInterface::MICROSECONDS_PER_SECOND;
    }
}

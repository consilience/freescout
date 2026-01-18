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
        // Alias class for backwards compatibility with Carbon 2.x code
    }
}

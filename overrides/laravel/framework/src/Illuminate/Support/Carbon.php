<?php

namespace Illuminate\Support;

use Carbon\CarbonImmutable as BaseCarbon;

/**
 * Laravel Carbon wrapper for Carbon 3.x compatibility.
 *
 * Carbon 3 uses CarbonImmutable as the primary class.
 * The Macroable trait is not needed as Carbon 3 has built-in macro support.
 */
class Carbon extends BaseCarbon
{
    /**
     * The custom Carbon JSON serializer.
     *
     * @var callable|string|null
     */
    protected static $serializer;

    /**
     * Prepare the object for JSON serialization.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        if (static::$serializer) {
            if (is_callable(static::$serializer)) {
                return call_user_func(static::$serializer, $this);
            }

            return parent::jsonSerialize();
        }

        return parent::jsonSerialize();
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for helper functions added for Laravel 12 compatibility.
 */
class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure helpers are loaded
        require_once __DIR__ . '/../../overrides/laravel/framework/src/Illuminate/Support/helpers.php';
    }

    // ==========================================
    // Tests for laravel_cloud() function
    // ==========================================

    public function testLaravelCloudFunctionExists(): void
    {
        $this->assertTrue(function_exists('laravel_cloud'));
    }

    public function testLaravelCloudReturnsFalseByDefault(): void
    {
        // Ensure the env vars are not set
        $originalEnv = $_ENV['LARAVEL_CLOUD'] ?? null;
        $originalServer = $_SERVER['LARAVEL_CLOUD'] ?? null;

        unset($_ENV['LARAVEL_CLOUD']);
        unset($_SERVER['LARAVEL_CLOUD']);

        $this->assertFalse(laravel_cloud());

        // Restore
        if ($originalEnv !== null) {
            $_ENV['LARAVEL_CLOUD'] = $originalEnv;
        }
        if ($originalServer !== null) {
            $_SERVER['LARAVEL_CLOUD'] = $originalServer;
        }
    }

    public function testLaravelCloudReturnsTrueWhenEnvSet(): void
    {
        $original = $_ENV['LARAVEL_CLOUD'] ?? null;

        $_ENV['LARAVEL_CLOUD'] = true;
        $this->assertTrue(laravel_cloud());

        // Restore
        if ($original !== null) {
            $_ENV['LARAVEL_CLOUD'] = $original;
        } else {
            unset($_ENV['LARAVEL_CLOUD']);
        }
    }

    public function testLaravelCloudReturnsTrueWhenServerSet(): void
    {
        $original = $_SERVER['LARAVEL_CLOUD'] ?? null;

        $_SERVER['LARAVEL_CLOUD'] = true;
        $this->assertTrue(laravel_cloud());

        // Restore
        if ($original !== null) {
            $_SERVER['LARAVEL_CLOUD'] = $original;
        } else {
            unset($_SERVER['LARAVEL_CLOUD']);
        }
    }

    // ==========================================
    // Tests for other common helper functions
    // ==========================================

    public function testAppendConfigFunctionExists(): void
    {
        $this->assertTrue(function_exists('append_config'));
    }

    public function testAppendConfig(): void
    {
        $array = [
            0 => 'first',
            1 => 'second',
            'key' => 'value'
        ];

        $result = append_config($array);

        // Numeric keys should be replaced with high values
        $this->assertArrayHasKey('key', $result);
        $this->assertEquals('value', $result['key']);

        // Numeric items should have been moved to high keys
        $this->assertContains('first', $result);
        $this->assertContains('second', $result);
    }

    public function testArrayAddFunctionExists(): void
    {
        $this->assertTrue(function_exists('array_add'));
    }

    public function testArrayAdd(): void
    {
        $array = ['name' => 'Desk'];
        $result = array_add($array, 'price', 100);

        $this->assertEquals(['name' => 'Desk', 'price' => 100], $result);
    }

    public function testArrayAddDoesNotOverwrite(): void
    {
        $array = ['name' => 'Desk', 'price' => 50];
        $result = array_add($array, 'price', 100);

        // Should not overwrite existing key
        $this->assertEquals(50, $result['price']);
    }

    public function testCollapseFunctionExists(): void
    {
        $this->assertTrue(function_exists('array_collapse'));
    }

    public function testArrayCollapse(): void
    {
        $array = [[1, 2], [3, 4], [5]];
        $result = array_collapse($array);

        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function testDataGetFunctionExists(): void
    {
        $this->assertTrue(function_exists('data_get'));
    }

    public function testDataGet(): void
    {
        $data = ['user' => ['name' => 'John', 'email' => 'john@example.com']];

        $this->assertEquals('John', data_get($data, 'user.name'));
        $this->assertEquals('john@example.com', data_get($data, 'user.email'));
        $this->assertNull(data_get($data, 'user.phone'));
        $this->assertEquals('default', data_get($data, 'user.phone', 'default'));
    }

    public function testDataSetFunctionExists(): void
    {
        $this->assertTrue(function_exists('data_set'));
    }

    public function testDataSet(): void
    {
        $data = ['user' => ['name' => 'John']];
        data_set($data, 'user.email', 'john@example.com');

        $this->assertEquals('john@example.com', $data['user']['email']);
    }

    public function testHeadFunctionExists(): void
    {
        $this->assertTrue(function_exists('head'));
    }

    public function testHead(): void
    {
        $this->assertEquals(1, head([1, 2, 3]));
        $this->assertEquals('a', head(['a', 'b', 'c']));
    }

    public function testLastFunctionExists(): void
    {
        $this->assertTrue(function_exists('last'));
    }

    public function testLast(): void
    {
        $this->assertEquals(3, last([1, 2, 3]));
        $this->assertEquals('c', last(['a', 'b', 'c']));
    }

    public function testValueFunctionExists(): void
    {
        $this->assertTrue(function_exists('value'));
    }

    public function testValue(): void
    {
        $this->assertEquals('foo', value('foo'));
        $this->assertEquals('bar', value(function () {
            return 'bar';
        }));
    }

    public function testWithFunctionExists(): void
    {
        $this->assertTrue(function_exists('with'));
    }

    public function testWith(): void
    {
        $result = with('foo', function ($value) {
            return $value . 'bar';
        });

        $this->assertEquals('foobar', $result);
    }

    public function testWithoutCallback(): void
    {
        $this->assertEquals('foo', with('foo'));
    }

    public function testBlankFunctionExists(): void
    {
        $this->assertTrue(function_exists('blank'));
    }

    public function testBlank(): void
    {
        $this->assertTrue(blank(''));
        $this->assertTrue(blank(null));
        $this->assertTrue(blank([]));
        $this->assertFalse(blank('foo'));
        $this->assertFalse(blank([1]));
        $this->assertFalse(blank(0));
        $this->assertFalse(blank(false));
    }

    public function testFilledFunctionExists(): void
    {
        $this->assertTrue(function_exists('filled'));
    }

    public function testFilled(): void
    {
        $this->assertFalse(filled(''));
        $this->assertFalse(filled(null));
        $this->assertFalse(filled([]));
        $this->assertTrue(filled('foo'));
        $this->assertTrue(filled([1]));
        $this->assertTrue(filled(0));
        $this->assertTrue(filled(false));
    }

    public function testObjectGetFunctionExists(): void
    {
        $this->assertTrue(function_exists('object_get'));
    }

    public function testObjectGet(): void
    {
        $object = new \stdClass();
        $object->name = 'John';
        $object->email = 'john@example.com';

        $this->assertEquals('John', object_get($object, 'name'));
        $this->assertEquals('john@example.com', object_get($object, 'email'));
        $this->assertNull(object_get($object, 'phone'));
        $this->assertEquals('default', object_get($object, 'phone', 'default'));
    }

    public function testOptionalFunctionExists(): void
    {
        $this->assertTrue(function_exists('optional'));
    }

    public function testOptional(): void
    {
        $object = new \stdClass();
        $object->name = 'John';

        $this->assertEquals('John', optional($object)->name);
        $this->assertNull(optional(null)->name);
    }

    public function testClassBasenameFunctionExists(): void
    {
        $this->assertTrue(function_exists('class_basename'));
    }

    public function testClassBasename(): void
    {
        $this->assertEquals('Foo', class_basename('App\\Models\\Foo'));
        $this->assertEquals('Bar', class_basename('Bar'));
    }

    public function testEFunctionExists(): void
    {
        $this->assertTrue(function_exists('e'));
    }

    public function testE(): void
    {
        $this->assertEquals('&lt;script&gt;', e('<script>'));
        $this->assertEquals('&amp;', e('&'));
        $this->assertEquals('&quot;', e('"'));
    }

    public function testTapFunctionExists(): void
    {
        $this->assertTrue(function_exists('tap'));
    }

    public function testTap(): void
    {
        $object = new \stdClass();
        $object->value = 0;

        $result = tap($object, function ($obj) {
            $obj->value = 10;
        });

        $this->assertSame($object, $result);
        $this->assertEquals(10, $object->value);
    }
}

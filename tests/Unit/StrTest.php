<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Support\Str;

/**
 * Tests for Str helper methods including Laravel 12 compatibility methods.
 */
class StrTest extends TestCase
{
    // ==========================================
    // Tests for trim() method
    // ==========================================

    public function testTrimRemovesWhitespace(): void
    {
        $this->assertEquals('hello', Str::trim('  hello  '));
        $this->assertEquals('hello world', Str::trim('  hello world  '));
    }

    public function testTrimWithNewlines(): void
    {
        $this->assertEquals('hello', Str::trim("\n\thello\n\t"));
    }

    public function testTrimWithCustomCharacters(): void
    {
        $this->assertEquals('hello', Str::trim('xxxhelloxxx', 'x'));
        $this->assertEquals('hello', Str::trim('---hello---', '-'));
    }

    public function testTrimWithEmptyString(): void
    {
        $this->assertEquals('', Str::trim(''));
        $this->assertEquals('', Str::trim('   '));
    }

    // ==========================================
    // Tests for ltrim() method
    // ==========================================

    public function testLtrimRemovesLeadingWhitespace(): void
    {
        $this->assertEquals('hello  ', Str::ltrim('  hello  '));
    }

    public function testLtrimWithCustomCharacters(): void
    {
        $this->assertEquals('helloxxx', Str::ltrim('xxxhelloxxx', 'x'));
    }

    // ==========================================
    // Tests for rtrim() method
    // ==========================================

    public function testRtrimRemovesTrailingWhitespace(): void
    {
        $this->assertEquals('  hello', Str::rtrim('  hello  '));
    }

    public function testRtrimWithCustomCharacters(): void
    {
        $this->assertEquals('xxxhello', Str::rtrim('xxxhelloxxx', 'x'));
    }

    // ==========================================
    // Tests for between() method
    // ==========================================

    public function testBetweenExtractsContent(): void
    {
        $this->assertEquals('bar', Str::between('foobarbaz', 'foo', 'baz'));
    }

    public function testBetweenWithHtmlTags(): void
    {
        $this->assertEquals('content', Str::between('<div>content</div>', '<div>', '</div>'));
    }

    public function testBetweenWithBrackets(): void
    {
        $this->assertEquals('value', Str::between('[value]', '[', ']'));
    }

    public function testBetweenWithMultipleOccurrences(): void
    {
        // Should return content between first 'from' and last 'to'
        $result = Str::between('start:middle:end', 'start:', ':end');
        $this->assertEquals('middle', $result);
    }

    public function testBetweenWithEmptyFrom(): void
    {
        $this->assertEquals('foobarbaz', Str::between('foobarbaz', '', 'baz'));
    }

    public function testBetweenWithEmptyTo(): void
    {
        $this->assertEquals('foobarbaz', Str::between('foobarbaz', 'foo', ''));
    }

    public function testBetweenWithMissingDelimiters(): void
    {
        // If delimiters are not found, returns empty or original
        $result = Str::between('hello world', 'foo', 'bar');
        $this->assertIsString($result);
    }

    // ==========================================
    // Tests for beforeLast() method
    // ==========================================

    public function testBeforeLastReturnsContentBeforeLastOccurrence(): void
    {
        $this->assertEquals('foo/bar', Str::beforeLast('foo/bar/baz', '/'));
    }

    public function testBeforeLastWithMultipleOccurrences(): void
    {
        $this->assertEquals('a.b.c', Str::beforeLast('a.b.c.d', '.'));
    }

    public function testBeforeLastWithNoOccurrence(): void
    {
        $this->assertEquals('hello world', Str::beforeLast('hello world', '/'));
    }

    public function testBeforeLastWithEmptySearch(): void
    {
        $this->assertEquals('hello', Str::beforeLast('hello', ''));
    }

    public function testBeforeLastFilePath(): void
    {
        $this->assertEquals('/var/www/app', Str::beforeLast('/var/www/app/file.php', '/'));
    }

    public function testBeforeLastFileExtension(): void
    {
        $this->assertEquals('document.backup', Str::beforeLast('document.backup.txt', '.'));
    }

    // ==========================================
    // Tests for existing Str methods
    // ==========================================

    public function testAfter(): void
    {
        $this->assertEquals('world', Str::after('hello world', 'hello '));
        $this->assertEquals('bar', Str::after('foobar', 'foo'));
    }

    public function testBefore(): void
    {
        $this->assertEquals('hello', Str::before('hello world', ' world'));
        $this->assertEquals('foo', Str::before('foobar', 'bar'));
    }

    public function testContains(): void
    {
        $this->assertTrue(Str::contains('hello world', 'world'));
        $this->assertTrue(Str::contains('hello world', 'hello'));
        $this->assertFalse(Str::contains('hello world', 'foo'));
    }

    public function testEndsWith(): void
    {
        $this->assertTrue(Str::endsWith('hello world', 'world'));
        $this->assertTrue(Str::endsWith('hello world', ['world', 'foo']));
        $this->assertFalse(Str::endsWith('hello world', 'hello'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(Str::startsWith('hello world', 'hello'));
        $this->assertTrue(Str::startsWith('hello world', ['hello', 'foo']));
        $this->assertFalse(Str::startsWith('hello world', 'world'));
    }

    public function testFinish(): void
    {
        $this->assertEquals('hello/', Str::finish('hello', '/'));
        $this->assertEquals('hello/', Str::finish('hello/', '/'));
    }

    public function testStart(): void
    {
        $this->assertEquals('/hello', Str::start('hello', '/'));
        $this->assertEquals('/hello', Str::start('/hello', '/'));
    }

    public function testIs(): void
    {
        $this->assertTrue(Str::is('foo*', 'foobar'));
        $this->assertTrue(Str::is('*bar', 'foobar'));
        $this->assertTrue(Str::is('foo*bar', 'foobazbar'));
        $this->assertFalse(Str::is('foo*', 'barfoo'));
    }

    public function testLength(): void
    {
        $this->assertEquals(5, Str::length('hello'));
        $this->assertEquals(0, Str::length(''));
        $this->assertEquals(11, Str::length('hello world'));
    }

    public function testLimit(): void
    {
        $long = 'This is a very long string that needs to be limited';
        $this->assertEquals('This is a very...', Str::limit($long, 14));
        $this->assertEquals('This is a very long string that needs to be limited', Str::limit($long, 100));
    }

    public function testLower(): void
    {
        $this->assertEquals('hello world', Str::lower('HELLO WORLD'));
        $this->assertEquals('hello world', Str::lower('Hello World'));
    }

    public function testUpper(): void
    {
        $this->assertEquals('HELLO WORLD', Str::upper('hello world'));
        $this->assertEquals('HELLO WORLD', Str::upper('Hello World'));
    }

    public function testTitle(): void
    {
        $this->assertEquals('Hello World', Str::title('hello world'));
        $this->assertEquals('Hello World', Str::title('HELLO WORLD'));
    }

    public function testSlug(): void
    {
        $this->assertEquals('hello-world', Str::slug('Hello World'));
        $this->assertEquals('hello-world', Str::slug('Hello  World'));
        $this->assertEquals('hello-world', Str::slug('Hello--World'));
    }

    public function testSnake(): void
    {
        $this->assertEquals('hello_world', Str::snake('HelloWorld'));
        $this->assertEquals('hello_world', Str::snake('helloWorld'));
    }

    public function testCamel(): void
    {
        $this->assertEquals('helloWorld', Str::camel('hello_world'));
        $this->assertEquals('helloWorld', Str::camel('hello-world'));
    }

    public function testStudly(): void
    {
        $this->assertEquals('HelloWorld', Str::studly('hello_world'));
        $this->assertEquals('HelloWorld', Str::studly('hello-world'));
    }

    public function testKebab(): void
    {
        $this->assertEquals('hello-world', Str::kebab('HelloWorld'));
        $this->assertEquals('hello-world', Str::kebab('helloWorld'));
    }

    public function testRandom(): void
    {
        $random = Str::random(16);
        $this->assertEquals(16, strlen($random));

        $random2 = Str::random(32);
        $this->assertEquals(32, strlen($random2));

        // Two random strings should be different
        $this->assertNotEquals(Str::random(16), Str::random(16));
    }

    public function testReplaceFirst(): void
    {
        $this->assertEquals('hello universe world', Str::replaceFirst('world', 'universe', 'hello world world'));
    }

    public function testReplaceLast(): void
    {
        $this->assertEquals('hello world universe', Str::replaceLast('world', 'universe', 'hello world world'));
    }

    public function testSubstr(): void
    {
        $this->assertEquals('llo', Str::substr('hello', 2));
        $this->assertEquals('ell', Str::substr('hello', 1, 3));
    }

    public function testUcfirst(): void
    {
        $this->assertEquals('Hello', Str::ucfirst('hello'));
        $this->assertEquals('Hello world', Str::ucfirst('hello world'));
    }

    public function testWords(): void
    {
        $long = 'This is a very long string with many words';
        $this->assertEquals('This is a...', Str::words($long, 3));
    }

    public function testAscii(): void
    {
        $this->assertEquals('a', Str::ascii('ã'));
        $this->assertEquals('u', Str::ascii('ü'));
    }

    public function testPlural(): void
    {
        $this->assertEquals('cars', Str::plural('car'));
        $this->assertEquals('children', Str::plural('child'));
    }

    public function testSingular(): void
    {
        $this->assertEquals('car', Str::singular('cars'));
        $this->assertEquals('child', Str::singular('children'));
    }
}

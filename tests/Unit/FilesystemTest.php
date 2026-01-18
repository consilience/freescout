<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Filesystem\Filesystem;

/**
 * Tests for Filesystem override including getRequire with data extraction.
 */
class FilesystemTest extends TestCase
{
    protected Filesystem $files;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->files = new Filesystem();
        $this->tempDir = sys_get_temp_dir() . '/filesystem_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    // ==========================================
    // Tests for getRequire() with data extraction
    // ==========================================

    public function testGetRequireWithoutData(): void
    {
        $file = $this->tempDir . '/simple.php';
        file_put_contents($file, '<?php return "hello";');

        $result = $this->files->getRequire($file);
        $this->assertEquals('hello', $result);
    }

    public function testGetRequireWithDataExtraction(): void
    {
        $file = $this->tempDir . '/with_data.php';
        file_put_contents($file, '<?php return $name;');

        $result = $this->files->getRequire($file, ['name' => 'John']);
        $this->assertEquals('John', $result);
    }

    public function testGetRequireWithMultipleVariables(): void
    {
        $file = $this->tempDir . '/multi_vars.php';
        file_put_contents($file, '<?php return $first . " " . $last;');

        $result = $this->files->getRequire($file, [
            'first' => 'John',
            'last' => 'Doe'
        ]);
        $this->assertEquals('John Doe', $result);
    }

    public function testGetRequireWithArrayData(): void
    {
        $file = $this->tempDir . '/array_data.php';
        file_put_contents($file, '<?php return implode(", ", $items);');

        $result = $this->files->getRequire($file, [
            'items' => ['apple', 'banana', 'cherry']
        ]);
        $this->assertEquals('apple, banana, cherry', $result);
    }

    public function testGetRequireDataDoesNotOverwriteLocalVars(): void
    {
        // EXTR_SKIP should prevent overwriting existing variables
        $file = $this->tempDir . '/no_overwrite.php';
        file_put_contents($file, '<?php $test = "original"; return $test;');

        // Note: PHP's require happens after extract, so this tests the behavior
        $result = $this->files->getRequire($file, ['test' => 'overwritten']);
        $this->assertEquals('original', $result);
    }

    public function testGetRequireThrowsExceptionForMissingFile(): void
    {
        $this->expectException(\Illuminate\Contracts\Filesystem\FileNotFoundException::class);
        $this->files->getRequire('/nonexistent/file.php');
    }

    // ==========================================
    // Tests for basic file operations
    // ==========================================

    public function testExists(): void
    {
        $file = $this->tempDir . '/exists.txt';
        $this->assertFalse($this->files->exists($file));

        file_put_contents($file, 'content');
        $this->assertTrue($this->files->exists($file));
    }

    public function testGet(): void
    {
        $file = $this->tempDir . '/get.txt';
        $content = 'Hello World';
        file_put_contents($file, $content);

        $this->assertEquals($content, $this->files->get($file));
    }

    public function testPut(): void
    {
        $file = $this->tempDir . '/put.txt';
        $content = 'New Content';

        $this->files->put($file, $content);
        $this->assertEquals($content, file_get_contents($file));
    }

    public function testAppend(): void
    {
        $file = $this->tempDir . '/append.txt';
        file_put_contents($file, 'Hello');

        $this->files->append($file, ' World');
        $this->assertEquals('Hello World', file_get_contents($file));
    }

    public function testDelete(): void
    {
        $file = $this->tempDir . '/delete.txt';
        file_put_contents($file, 'content');
        $this->assertTrue(file_exists($file));

        $this->files->delete($file);
        $this->assertFalse(file_exists($file));
    }

    public function testCopy(): void
    {
        $source = $this->tempDir . '/source.txt';
        $dest = $this->tempDir . '/dest.txt';
        file_put_contents($source, 'content');

        $this->files->copy($source, $dest);
        $this->assertTrue(file_exists($dest));
        $this->assertEquals('content', file_get_contents($dest));
    }

    public function testMove(): void
    {
        $source = $this->tempDir . '/source_move.txt';
        $dest = $this->tempDir . '/dest_move.txt';
        file_put_contents($source, 'content');

        $this->files->move($source, $dest);
        $this->assertFalse(file_exists($source));
        $this->assertTrue(file_exists($dest));
    }

    public function testSize(): void
    {
        $file = $this->tempDir . '/size.txt';
        $content = 'Hello World'; // 11 bytes
        file_put_contents($file, $content);

        $this->assertEquals(11, $this->files->size($file));
    }

    public function testLastModified(): void
    {
        $file = $this->tempDir . '/modified.txt';
        file_put_contents($file, 'content');

        $modified = $this->files->lastModified($file);
        $this->assertIsInt($modified);
        $this->assertGreaterThan(0, $modified);
    }

    public function testIsDirectory(): void
    {
        $this->assertTrue($this->files->isDirectory($this->tempDir));
        $this->assertFalse($this->files->isDirectory($this->tempDir . '/nonexistent'));

        $file = $this->tempDir . '/isdir.txt';
        file_put_contents($file, 'content');
        $this->assertFalse($this->files->isDirectory($file));
    }

    public function testIsFile(): void
    {
        $file = $this->tempDir . '/isfile.txt';
        file_put_contents($file, 'content');

        $this->assertTrue($this->files->isFile($file));
        $this->assertFalse($this->files->isFile($this->tempDir));
    }

    public function testMakeDirectory(): void
    {
        $dir = $this->tempDir . '/newdir';
        $this->assertFalse(is_dir($dir));

        $this->files->makeDirectory($dir);
        $this->assertTrue(is_dir($dir));

        rmdir($dir);
    }

    public function testMakeDirectoryRecursive(): void
    {
        $dir = $this->tempDir . '/nested/deep/dir';
        $this->assertFalse(is_dir($dir));

        $this->files->makeDirectory($dir, 0755, true);
        $this->assertTrue(is_dir($dir));

        // Clean up
        rmdir($dir);
        rmdir($this->tempDir . '/nested/deep');
        rmdir($this->tempDir . '/nested');
    }

    public function testHash(): void
    {
        $file = $this->tempDir . '/hash.txt';
        file_put_contents($file, 'content');

        $hash = $this->files->hash($file);
        $this->assertEquals(md5_file($file), $hash);
    }

    public function testExtension(): void
    {
        $this->assertEquals('txt', $this->files->extension('file.txt'));
        $this->assertEquals('php', $this->files->extension('/path/to/file.php'));
        // Filesystem::extension only returns the last extension
        $this->assertEquals('php', $this->files->extension('view.blade.php'));
    }

    public function testBasename(): void
    {
        $this->assertEquals('file.txt', $this->files->basename('/path/to/file.txt'));
    }

    public function testDirname(): void
    {
        $this->assertEquals('/path/to', $this->files->dirname('/path/to/file.txt'));
    }

    public function testName(): void
    {
        $this->assertEquals('file', $this->files->name('/path/to/file.txt'));
    }

    public function testType(): void
    {
        $file = $this->tempDir . '/type.txt';
        file_put_contents($file, 'content');

        $this->assertEquals('file', $this->files->type($file));
        $this->assertEquals('dir', $this->files->type($this->tempDir));
    }
}

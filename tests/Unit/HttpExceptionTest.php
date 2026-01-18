<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Tests for HttpException override with Symfony 7 compatible return types.
 */
class HttpExceptionTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $exception = new HttpException(404);
        $this->assertInstanceOf(HttpExceptionInterface::class, $exception);
    }

    public function testGetStatusCodeReturnsInt(): void
    {
        $exception = new HttpException(404);
        $statusCode = $exception->getStatusCode();

        $this->assertIsInt($statusCode);
        $this->assertEquals(404, $statusCode);
    }

    public function testGetHeadersReturnsArray(): void
    {
        $exception = new HttpException(404, 'Not Found', null, ['X-Custom' => 'value']);
        $headers = $exception->getHeaders();

        $this->assertIsArray($headers);
        $this->assertEquals(['X-Custom' => 'value'], $headers);
    }

    public function testGetStatusCode200(): void
    {
        // Although unusual, testing edge case
        $exception = new HttpException(200);
        $this->assertEquals(200, $exception->getStatusCode());
    }

    public function testGetStatusCode400(): void
    {
        $exception = new HttpException(400);
        $this->assertEquals(400, $exception->getStatusCode());
    }

    public function testGetStatusCode401(): void
    {
        $exception = new HttpException(401);
        $this->assertEquals(401, $exception->getStatusCode());
    }

    public function testGetStatusCode403(): void
    {
        $exception = new HttpException(403);
        $this->assertEquals(403, $exception->getStatusCode());
    }

    public function testGetStatusCode404(): void
    {
        $exception = new HttpException(404);
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function testGetStatusCode500(): void
    {
        $exception = new HttpException(500);
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function testGetStatusCode503(): void
    {
        $exception = new HttpException(503);
        $this->assertEquals(503, $exception->getStatusCode());
    }

    public function testDefaultHeadersEmpty(): void
    {
        $exception = new HttpException(404);
        $this->assertEquals([], $exception->getHeaders());
    }

    public function testCustomHeaders(): void
    {
        $headers = [
            'X-Custom-Header' => 'custom-value',
            'X-Another' => 'another-value'
        ];
        $exception = new HttpException(404, 'Not Found', null, $headers);

        $this->assertEquals($headers, $exception->getHeaders());
    }

    public function testSetHeaders(): void
    {
        $exception = new HttpException(404);
        $exception->setHeaders(['X-New' => 'new-value']);

        $this->assertEquals(['X-New' => 'new-value'], $exception->getHeaders());
    }

    public function testMessage(): void
    {
        $exception = new HttpException(404, 'Resource not found');
        $this->assertEquals('Resource not found', $exception->getMessage());
    }

    public function testDefaultMessage(): void
    {
        $exception = new HttpException(404);
        $this->assertEquals('', $exception->getMessage());
    }

    public function testNullMessage(): void
    {
        $exception = new HttpException(404, null);
        $this->assertEquals('', $exception->getMessage());
    }

    public function testExceptionCode(): void
    {
        $exception = new HttpException(404, 'Not Found', null, [], 123);
        $this->assertEquals(123, $exception->getCode());
    }

    public function testDefaultCode(): void
    {
        $exception = new HttpException(404);
        $this->assertEquals(0, $exception->getCode());
    }

    public function testPreviousException(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new HttpException(500, 'Server error', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new HttpException(404);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testIsThrowable(): void
    {
        $exception = new HttpException(404);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Request;

/**
 * Tests for Request override compatibility with Symfony 7.
 *
 * Note: Some tests require the full Laravel container and are skipped.
 * These tests focus on the HEADER constants and basic request creation.
 */
class RequestTest extends TestCase
{
    public function testHeaderForwardedConstant(): void
    {
        $this->assertEquals(0b000001, Request::HEADER_FORWARDED);
    }

    public function testHeaderXForwardedForConstant(): void
    {
        $this->assertEquals(0b000010, Request::HEADER_X_FORWARDED_FOR);
    }

    public function testHeaderXForwardedHostConstant(): void
    {
        $this->assertEquals(0b000100, Request::HEADER_X_FORWARDED_HOST);
    }

    public function testHeaderXForwardedProtoConstant(): void
    {
        $this->assertEquals(0b001000, Request::HEADER_X_FORWARDED_PROTO);
    }

    public function testHeaderXForwardedPortConstant(): void
    {
        $this->assertEquals(0b010000, Request::HEADER_X_FORWARDED_PORT);
    }

    public function testHeaderXForwardedPrefixConstant(): void
    {
        $this->assertEquals(0b100000, Request::HEADER_X_FORWARDED_PREFIX);
    }

    public function testHeaderXForwardedAwsElbConstant(): void
    {
        $this->assertEquals(0b0011010, Request::HEADER_X_FORWARDED_AWS_ELB);
    }

    public function testHeaderXForwardedTraefikConstant(): void
    {
        $this->assertEquals(0b0111110, Request::HEADER_X_FORWARDED_TRAEFIK);
    }

    public function testConstantBitCombinations(): void
    {
        // AWS ELB should be FOR | PROTO | PORT (no HOST)
        $expected = Request::HEADER_X_FORWARDED_FOR |
                   Request::HEADER_X_FORWARDED_PROTO |
                   Request::HEADER_X_FORWARDED_PORT;
        $this->assertEquals($expected, Request::HEADER_X_FORWARDED_AWS_ELB);
    }

    public function testTraefikIncludesAllXForwarded(): void
    {
        // Traefik should include all X-Forwarded-* headers
        $expected = Request::HEADER_X_FORWARDED_FOR |
                   Request::HEADER_X_FORWARDED_HOST |
                   Request::HEADER_X_FORWARDED_PROTO |
                   Request::HEADER_X_FORWARDED_PORT |
                   Request::HEADER_X_FORWARDED_PREFIX;
        $this->assertEquals($expected, Request::HEADER_X_FORWARDED_TRAEFIK);
    }

    public function testRequestCreate(): void
    {
        $request = Request::create('/test', 'GET');
        $this->assertInstanceOf(Request::class, $request);
        $this->assertEquals('GET', $request->getMethod());
    }

    public function testRequestHostMethod(): void
    {
        $request = Request::create('http://example.com/test', 'GET');
        $this->assertEquals('example.com', $request->host());
    }

    public function testRequestHostMethodMatchesGetHost(): void
    {
        $request = Request::create('http://mysite.local/test', 'GET');
        $this->assertEquals($request->getHost(), $request->host());
    }

    public function testRequestMethod(): void
    {
        $request = Request::create('/test', 'POST');
        $this->assertEquals('POST', $request->method());
    }

    public function testRequestInput(): void
    {
        $request = Request::create('/test', 'POST', ['name' => 'John']);
        $this->assertEquals('John', $request->input('name'));
        $this->assertNull($request->input('nonexistent'));
        $this->assertEquals('default', $request->input('nonexistent', 'default'));
    }

    public function testRequestQuery(): void
    {
        $request = Request::create('/test?page=5&sort=name', 'GET');
        $this->assertEquals('5', $request->query('page'));
        $this->assertEquals('name', $request->query('sort'));
    }

    public function testRequestHas(): void
    {
        $request = Request::create('/test', 'POST', ['name' => 'John', 'email' => 'john@example.com']);
        $this->assertTrue($request->has('name'));
        $this->assertTrue($request->has('email'));
        $this->assertFalse($request->has('phone'));
    }

    public function testRequestMerge(): void
    {
        $request = Request::create('/test', 'POST', ['name' => 'John']);
        $request->merge(['email' => 'john@example.com']);
        $this->assertEquals('john@example.com', $request->input('email'));
    }

    public function testRequestIp(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);
        $this->assertEquals('192.168.1.1', $request->ip());
    }

    public function testRequestUserAgent(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Test Browser'
        ]);
        $this->assertEquals('Mozilla/5.0 Test Browser', $request->userAgent());
    }

    public function testRequestAjax(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);
        $this->assertTrue($request->ajax());

        $request = Request::create('/test', 'GET');
        $this->assertFalse($request->ajax());
    }

    public function testRequestAll(): void
    {
        $request = Request::create('/test', 'POST', ['name' => 'John', 'email' => 'john@example.com']);
        $all = $request->all();
        $this->assertIsArray($all);
        $this->assertEquals('John', $all['name']);
        $this->assertEquals('john@example.com', $all['email']);
    }

    public function testRequestOnly(): void
    {
        $request = Request::create('/test', 'POST', ['name' => 'John', 'email' => 'john@example.com', 'phone' => '123']);
        $only = $request->only(['name', 'email']);
        $this->assertArrayHasKey('name', $only);
        $this->assertArrayHasKey('email', $only);
        $this->assertArrayNotHasKey('phone', $only);
    }

    public function testRequestExcept(): void
    {
        $request = Request::create('/test', 'POST', ['name' => 'John', 'email' => 'john@example.com', 'phone' => '123']);
        $except = $request->except(['phone']);
        $this->assertArrayHasKey('name', $except);
        $this->assertArrayHasKey('email', $except);
        $this->assertArrayNotHasKey('phone', $except);
    }

    public function testRequestJson(): void
    {
        $request = Request::create('/test', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], '{"name": "John"}');

        $json = $request->json();
        $this->assertNotNull($json);
    }

    public function testRequestGetPort(): void
    {
        $request = Request::create('http://example.com:8080/test', 'GET');
        $this->assertEquals(8080, $request->getPort());
    }

    public function testRequestIsMethod(): void
    {
        $request = Request::create('/test', 'POST');
        $this->assertTrue($request->isMethod('POST'));
        $this->assertTrue($request->isMethod('post'));
        $this->assertFalse($request->isMethod('GET'));
    }
}

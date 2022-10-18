<?php
namespace Tests\Http;

use Owl\Http\Request;

class RequestTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $request = \Owl\Http\Request::factory([
            'uri' => '/foobar?a=b',
            'get' => [
                'foo' => '1',
                'bar' => '',
            ],
        ]);

        $this->assertEquals('b', $request->get('a'));
        $this->assertEquals('1', $request->get('foo'));
        $this->assertSame('', $request->get('bar'));
        $this->assertTrue($request->hasGet('bar'));
        $this->assertFalse($request->hasGet('baz'));
        $this->assertSame(['a' => 'b', 'foo' => '1', 'bar' => ''], $request->get());
    }

    public function testPost()
    {
        $request = \Owl\Http\Request::factory([
            'method' => 'post',
            'post' => [
                'foo' => '1',
                'bar' => '',
            ],
        ]);

        $this->assertEquals('1', $request->post('foo'));
        $this->assertSame('', $request->post('bar'));
        $this->assertTrue($request->hasPost('bar'));
        $this->assertFalse($request->hasPost('baz'));
        $this->assertSame(['foo' => '1', 'bar' => ''], $request->post());
    }

    public function testCookie()
    {
        $request = \Owl\Http\Request::factory([
            'cookies' => [
                'foo' => '1',
                'bar' => '',
            ],
        ]);

        $this->assertEquals('1', $request->getCookieParam('foo'));
        $this->assertSame('', $request->getCookieParam('bar'));
        $this->assertSame(['foo' => '1', 'bar' => ''], $request->getCookies());
    }

    public function testHeaders()
    {
        $request = \Owl\Http\Request::factory([
            'headers' => [
                'Accept-Encoding' => 'gzip,deflate',
                'Accept-Language' => 'en-us,en;q=0.8,zh-cn;q=0.5,zh;q=0.3',
                'Connection' => 'keepalive',
            ],
        ]);

        $this->assertEquals(['gzip', 'deflate'], $request->getHeader('accept-encoding'));
        $this->assertEquals($request->getHeader('accept-encoding'), $request->getHeader('ACCEPT-ENCODING'));
        $this->assertSame([
            'accept-encoding' => ['gzip', 'deflate'],
            'accept-language' => ['en-us', 'en;q=0.8', 'zh-cn;q=0.5', 'zh;q=0.3'],
            'connection' => ['keepalive'],
        ], $request->getHeaders());
    }

    public function testMethod()
    {
        foreach (['get', 'post', 'put', 'delete'] as $method) {
            $request = \Owl\Http\Request::factory([
                'method' => $method,
            ]);

            $this->assertEquals(strtoupper($method), $request->getMethod());
            $this->assertTrue(call_user_func([$request, 'is' . $method]));
        }

        $request = \Owl\Http\Request::factory([
            'method' => 'POST',
            'post' => [
                '_method' => 'PUT',
            ],
        ]);
        $this->assertEquals('PUT', $request->getMethod());

        $request = \Owl\Http\Request::factory([
            'method' => 'POST',
            'headers' => [
                'x-http-method-override' => 'DELETE',
            ],
            'post' => [
                'foo' => 'bar',
            ],
        ]);
        $this->assertEquals('DELETE', $request->getMethod());
    }

    public function testRequestURI()
    {
        $uri = '/foobar.json?foo=bar';
        $request = \Owl\Http\Request::factory([
            'uri' => $uri,
        ]);

        $this->assertEquals($uri, $request->getRequestTarget());
        $this->assertEquals('/foobar.json', $request->getUri()->getPath());
        $this->assertEquals('json', $request->getUri()->getExtension());

        $request = \Owl\Http\Request::factory([
            'uri' => '/',
            '_SERVER' => [
                'SERVER_NAME' => 'test.example.com',
            ],
        ]);

        $uri = $request->getUri();
        $this->assertEquals('test.example.com', $uri->getHost());

        $request = \Owl\Http\Request::factory([
            'uri' => '/',
            'headers' => [
                'host' => 'www.example.com:88',
            ],
            '_SERVER' => [
                'SERVER_NAME' => 'test.example.com',
            ],
        ]);

        $uri = $request->getUri();
        $this->assertEquals('www.example.com', $uri->getHost());
        $this->assertEquals(88, $uri->getPort());
    }

    public function testGetIP()
    {
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.2,3.3.3.3',
        ];

        $request = new \Owl\Http\Request([], [], $server);

        $this->assertEquals('127.0.0.1', $request->getClientIP());

        $request->allowClientProxyIP();
        $this->assertEquals('3.3.3.3', $request->getClientIP());

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        $server = [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '192.168.1.2,192.168.1.3',
        ];

        $request = new \Owl\Http\Request([], [], $server);

        $request->allowClientProxyIP();
        $this->assertEquals('192.168.1.2', $request->getClientIP());

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        $ip_extractor = function($request, $allow_client_proxy_ip) {
            /**
             * @var Request $request
             * @var bool $allow_client_proxy_ip
             */
            if ($clientIp = $request->getHeaderLine('x-real-client-ip')) {
                return $clientIp;
            }

            if ($xrip = $request->getHeaderLine('X-Real-IP')) {
                return $xrip;
            }

            if (!$allow_client_proxy_ip || !($ip = $request->getServerParam('http_x_forwarded_for'))) {
                return $request->getServerParam('remote_addr');
            }

            if (false === strpos($ip, ',')) {
                return $ip;
            }

            // private ip range, ip2long()
            $private = [
                [0, 50331647],            // 0.0.0.0, 2.255.255.255
                [167772160, 184549375],   // 10.0.0.0, 10.255.255.255
                [2130706432, 2147483647], // 127.0.0.0, 127.255.255.255
                [2851995648, 2852061183], // 169.254.0.0, 169.254.255.255
                [2886729728, 2887778303], // 172.16.0.0, 172.31.255.255
                [3221225984, 3221226239], // 192.0.2.0, 192.0.2.255
                [3232235520, 3232301055], // 192.168.0.0, 192.168.255.255
                [4294967040, 4294967295], // 255.255.255.0 255.255.255.255
            ];

            $ip_set = array_map('trim', explode(',', $ip));

            // 检查是否私有地址，如果不是就直接返回
            foreach ($ip_set as $key => $ip) {
                $long = ip2long($ip);

                if (false === $long) {
                    unset($ip_set[$key]);
                    continue;
                }

                $is_private = false;

                foreach ($private as $m) {
                    list($min, $max) = $m;
                    if ($long >= $min && $long <= $max) {
                        $is_private = true;
                        break;
                    }
                }

                if (!$is_private) {
                    return $ip;
                }
            }

            return array_shift($ip_set) ?: '0.0.0.0';
        };
        $request = \Owl\Http\Request::factory([
            'uri' => '/',
            'method' => 'GET',
            'cookies' => [],
            'headers' => [
                "x-hc-date" => "2022-10-16T19:17:14.99672+08:00","via" => "cn1575.l1, l2su121-6.l2, l2st3-1.l2",
                "x-forwarded-for" => "60.205.218.181, 119.23.91.192, 127.0.0.1",
                "ali-cdn-real-ip" => "60.205.218.181",
                "content-type" => "application/x-www-form-urlencoded",
                "x-alicdn-da-via" => "47.105.29.104,59.36.94.249,119.23.91.244",
                "x-real-client-ip" => "42.2.87.59",
            ],
            'get' => [],
            'post' => [],
            'ip' => '',
            '_SERVER' => [],
        ]);
        $request->setClientIpExtractor($ip_extractor);
        $request->allowClientProxyIP();
        $this->assertEquals('42.2.87.59', $request->getClientIP());

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        $request = \Owl\Http\Request::factory([
            'uri' => '/',
            'method' => 'GET',
            'cookies' => [],
            'headers' => [],
            'get' => [],
            'post' => [],
            'ip' => '',
            '_SERVER' => [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_X_FORWARDED_FOR' => '192.168.1.2,192.168.1.3',
            ],
        ]);
        $request->setClientIpExtractor($ip_extractor);
        $request->allowClientProxyIP();
        $this->assertEquals('192.168.1.2', $request->getClientIP());
    }
}

<?php

namespace Owl\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class Request implements ServerRequestInterface
{
    use \Owl\Http\MessageTrait;

    protected $get;
    protected $post;
    protected $cookies;
    protected $files;
    protected $method;
    protected $uri;
    protected $allow_client_proxy_ip = false;

    public function __construct($get = null, $post = null, $server = null, $cookies = null, $files = null)
    {
        $this->get = null === $get ? $_GET : $get;
        $this->post = null === $post ? $_POST : $post;
        $this->server = null === $server ? $_SERVER : $server;
        $this->cookies = null === $cookies ? $_COOKIE : $cookies;
        $this->files = null === $files ? $_FILES : $files;

        $this->initialize();
    }

    public function __clone()
    {
        $this->method = null;
        $this->uri = null;
    }

    /**
     * @param string $key
     *
     * @return mixed | array
     */
    public function get($key = null)
    {
        if (null === $key) {
            return $this->get;
        }

        return isset($this->get[$key]) ? $this->get[$key] : null;
    }

    /**
     * @param string $key
     *
     * @return mixed | array
     */
    public function post($key = null)
    {
        if (null === $key) {
            return $this->post;
        }

        return isset($this->post[$key]) ? $this->post[$key] : null;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasGet($key)
    {
        return array_key_exists($key, $this->get);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasPost($key)
    {
        return array_key_exists($key, $this->post);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getRequestTarget()
    {
        return isset($this->server['REQUEST_URI']) ? $this->server['REQUEST_URI'] : '/';
    }

    /**
     * {@inheritdoc}
     *
     * @return self
     */
    public function withRequestTarget($requestTarget)
    {
        $result = clone $this;

        $result->server['REQUEST_URI'] = $requestTarget;

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getMethod()
    {
        if (null !== $this->method) {
            return $this->method;
        }

        $method = isset($this->server['REQUEST_METHOD']) ? strtoupper($this->server['REQUEST_METHOD']) : 'GET';
        if ('POST' !== $method) {
            return $this->method = $method;
        }

        $override = $this->getHeader('x-http-method-override') ?: $this->post('_method');
        if ($override) {
            if (is_array($override)) {
                $override = array_shift($override);
            }

            $method = $override;
        }

        return $this->method = strtoupper($method);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $method
     *
     * @return self
     */
    public function withMethod($method)
    {
        $result = clone $this;
        $result->method = strtoupper($method);

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Owl\Http\Uri
     */
    public function getUri()
    {
        if ($this->uri) {
            return $this->uri;
        }

        $scheme = $this->getServerParam('HTTPS') ? 'https' : 'http';
        $user = $this->getServerParam('PHP_AUTH_USER');
        $password = $this->getServerParam('PHP_AUTH_PW');

        if ($http_host = $this->getServerParam('HTTP_HOST')) {
            if (false === strpos($http_host, ':')) {
                $host = $http_host;
                $port = 0;
            } else {
                list($host, $port) = explode(':', $http_host, 2);
                $port = intval($port);
            }
        } else {
            $host = $this->getServerParam('SERVER_NAME') ?: $this->getServerParam('SERVER_ADDR') ?: '127.0.0.1';
            $port = $this->getServerParam('SERVER_PORT');
        }

        $uri = (new Uri($this->getRequestTarget()))
            ->withScheme($scheme)
            ->withUserInfo($user, $password)
            ->withHost(strtolower($host));

        if ($port) {
            $uri = $uri->withPort($port);
        }

        return $uri;
    }

    /**
     * {@inheritdoc}
     *
     * @param \Psr\Http\Message\UriInterface $uri
     * @param bool                           $preserveHost
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        throw new \Exception('Request::withUri() not implemented');
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getServerParams()
    {
        return $this->server;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $name
     *
     * @return mixed | false
     */
    public function getServerParam($name)
    {
        $name = strtoupper($name);

        return isset($this->server[$name]) ? $this->server[$name] : false;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getCookieParams()
    {
        return $this->cookies;
    }

    /**
     * {@inheritdoc}
     *
     * @param string $name
     *
     * @return mixed | false
     */
    public function getCookieParam($name)
    {
        return isset($this->cookies[$name]) ? $this->cookies[$name] : false;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $cookies
     *
     * @return self
     */
    public function withCookieParams(array $cookies)
    {
        $result = clone $this;

        $result->cookies = $cookies;

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getQueryParams()
    {
        return $this->get;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $query
     *
     * @return self
     */
    public function withQueryParams(array $query)
    {
        $result = clone $this;

        $result->get = $query;

        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getUploadedFiles()
    {
        $files = [];

        foreach (self::normailizeUploadedFiles($this->files) as $key => $file) {
            if (isset($file['name'])) {
                $files[$key] = new UploadedFile($file);
            } else {
                foreach ($file as $f) {
                    if ('' === $f['name']) {
                        continue;
                    }

                    $files[$key][] = new UploadedFile($f);
                }
            }
        }

        return $files;
    }

    /**
     * {@inheritdoc}
     *
     * @param array $uploadFiles
     */
    public function withUploadedFiles(array $uploadFiles)
    {
        throw new \Exception('Request::withUploadedFiles() not implemented');
    }

    /**
     * {@inheritdoc}
     *
     * @return string | array
     */
    public function getParsedBody()
    {
        $content_type = $this->getHeaderLine('content-type');
        $method = $this->getServerParam('REQUEST_METHOD');

        if ('POST' === $method && (false !== \strpos($content_type, 'application/x-www-form-urlencoded') || false !== \strpos($content_type, 'multipart/form-data'))) {
            return $this->post;
        }

        $body = (string) $this->body;

        if ('' === $body) {
            return;
        }

        if (false !== \strpos($content_type, 'application/json')) {
            return \Owl\safe_json_decode($body, true);
        }

        return $body;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $data
     */
    public function withParsedBody($data)
    {
        throw new \Exception('Request::withParsedBody() not implemented');
    }

    public function allowClientProxyIP()
    {
        $this->allow_client_proxy_ip = true;
    }

    public function disallowClientProxyIP()
    {
        $this->allow_client_proxy_ip = false;
    }

    /**
     * @return string
     */
    public function getClientIP()
    {
        if (!$this->allow_client_proxy_ip || !($ip = $this->getServerParam('http_x_forwarded_for'))) {
            return $this->getServerParam('remote_addr');
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
    }

    /**
     * @return bool
     */
    public function isGet()
    {
        return 'GET' === $this->getMethod() || 'HEAD' === $this->getMethod();
    }

    /**
     * @return bool
     */
    public function isPost()
    {
        return 'POST' === $this->getMethod();
    }

    /**
     * @return bool
     */
    public function isPut()
    {
        return 'PUT' === $this->getMethod();
    }

    /**
     * @return bool
     */
    public function isDelete()
    {
        return 'DELETE' === $this->getMethod();
    }

    /**
     * @return bool
     */
    public function isAjax()
    {
        $val = $this->getHeader('x-requested-with');

        return $val && ('xmlhttprequest' === strtolower($val[0]));
    }

    protected function initialize()
    {
        $this->body = new ResourceStream(fopen('php://input', 'r'));

        $headers = [];
        foreach ($this->server as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $key = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$key] = explode(',', $value);
            }
        }
        $this->headers = $headers;
    }

    /**
     * 构造http请求对象，供测试使用.
     *
     * @example
     * $request = Request::factory([
     *     'uri' => '/',
     *     'method' => 'post',
     *     'cookies' => [
     *         $key => $value,
     *         ...
     *     ],
     *     'headers' => [
     *         $key => $value,
     *         ...
     *     ],
     *     'get' => [
     *         $key => $value,
     *         ...
     *     ],
     *     'post' => [
     *         $key => $value,
     *         ...
     *     ],
     * ]);
     */
    public static function factory(array $options = [])
    {
        $options = array_merge([
            'uri' => '/',
            'method' => 'GET',
            'cookies' => [],
            'headers' => [],
            'get' => [],
            'post' => [],
            'ip' => '',
            '_SERVER' => [],
        ], $options);

        $server = array_change_key_case($options['_SERVER'], CASE_UPPER);
        $server['REQUEST_METHOD'] = strtoupper($options['method']);
        $server['REQUEST_URI'] = $options['uri'];

        if ($options['ip']) {
            $server['REMOTE_ADDR'] = $options['ip'];
        }

        if ($query = parse_url($options['uri'], PHP_URL_QUERY)) {
            parse_str($query, $get);
            $options['get'] = array_merge($get, $options['get']);
        }

        $cookies = $options['cookies'];
        $get = $options['get'];
        $post = $options['post'];

        if ('GET' === $server['REQUEST_METHOD']) {
            $post = [];
        }

        foreach ($options['headers'] as $key => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $server[$key] = $value;
        }

        return new self($get, $post, $server, $cookies);
    }

    private static function normailizeUploadedFiles($files)
    {
        $result = [];

        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                foreach ($file['name'] as $i => $name) {
                    $result[$key][$i]['name'] = $name;
                    $result[$key][$i]['type'] = $file['type'][$i];
                    $result[$key][$i]['tmp_name'] = $file['tmp_name'][$i];
                    $result[$key][$i]['error'] = $file['error'][$i];
                    $result[$key][$i]['size'] = $file['size'][$i];
                }
            } else {
                $result[$key] = $file;
            }
        }

        return $result;
    }

    /**
     * @deprecated
     */
    public function getRequestURI()
    {
        return $this->getRequestTarget();
    }

    /**
     * @deprecated
     */
    public function getRequestPath()
    {
        return $this->getUri()->getPath();
    }

    /**
     * @deprecated
     */
    public function getExtension()
    {
        return $this->getUri()->getExtension();
    }

    /**
     * @deprecated
     */
    public function setParameter($key, $value)
    {
        return $this->withAttribute($key, $value);
    }

    /**
     * @deprecated
     */
    public function getParameter($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * @deprecated
     */
    public function getParameters()
    {
        return $this->getAttributes();
    }

    /**
     * @deprecated
     */
    public function getServer($key = null)
    {
        if (null === $key) {
            return $this->getServerParams();
        }

        return $this->getServerParam($key);
    }

    /**
     * @deprecated
     */
    public function getCookie($key)
    {
        return $this->getCookieParam($key);
    }

    /**
     * @deprecated
     */
    public function getCookies()
    {
        return $this->getCookieParams();
    }

    /**
     * @deprecated
     */
    public function getIP()
    {
        return $this->getClientIP();
    }
}

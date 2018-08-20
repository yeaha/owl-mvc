<?php

namespace Owl\Http;

class Response implements \Psr\Http\Message\ResponseInterface
{
    use \Owl\Http\MessageTrait;

    protected $code = 200;

    protected $reason_phrase = '';

    protected $cookies = [];

    protected $end = false;

    public function __construct()
    {
        $this->immutability = false;
        $this->reset();
    }

    public function reset()
    {
        $this->attributes = [];
        $this->code = 200;
        $this->cookies = [];
        $this->headers = [];
        $this->body = new StringStream();
        $this->end = false;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->code;
    }

    /**
     * {@inheritdoc}
     *
     * @param int    $code
     * @param string $reasonPhrase
     *
     * @return self
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $this->code = (int) $code;
        $this->reason_phrase = $reasonPhrase;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getReasonPhrase()
    {
        if ($this->reason_phrase) {
            return $this->reason_phrase;
        }

        return \Owl\Http::getStatusPhrase($this->code);
    }

    /**
     * @return array
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * @param string $name
     * @param mixed  $value
     * @param int    $expire
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     * @param bool   $httponly
     *
     * @return self
     */
    public function withCookie($name, $value, $expire = 0, $path = '/', $domain = null, $secure = null, $httponly = true)
    {
        if (null === $secure) {
            $secure = isset($_SERVER['HTTPS']) ? (bool) $_SERVER['HTTPS'] : false;
        }

        $key = sprintf('%s@%s:%s', $name, $domain, $path);
        $this->cookies[$key] = [$name, $value, $expire, $path, $domain, $secure, $httponly];

        return $this;
    }

    /**
     * @param \Owl\Http\Uri | string $url
     * @param int                    $status
     *
     * @return self
     */
    public function redirect($url, $status = 303)
    {
        return $this->withStatus($status)
                    ->withHeader('Location', $url);
    }

    /**
     * @param string $data
     *
     * @return self
     */
    public function write($data)
    {
        $this->getBody()->write($data);

        return $this;
    }

    /**
     * @param string $data
     *
     * @return self
     */
    public function end($data = null)
    {
        if ($this->end) {
            return $this;
        }

        $this->end = true;

        if (null !== $data) {
            $this->write($data);
        }

        $this->send();

        return $this;
    }

    protected function send()
    {
        $code = $this->getStatusCode();
        if (!headers_sent()) {
            $version = $this->getProtocolVersion();

            if (200 !== $code || '1.1' !== $version) {
                header(sprintf('HTTP/%s %d %s', $version, $code, $this->getReasonPhrase()));
            }

            foreach ($this->headers as $key => $value) {
                $key = ucwords(strtolower($key), '-');
                if (is_array($value)) {
                    $value = implode(',', $value);
                }

                header(sprintf('%s: %s', $key, $value));
            }

            foreach ($this->cookies as $cookie) {
                list($name, $value, $expire, $path, $domain, $secure, $httponly) = $cookie;
                setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
            }
        }

        $body = $this->getBody();
        if (204 === $code || 304 === $code) {
            echo '';
        } elseif ($body instanceof \Owl\Http\IteratorStream) {
            foreach ($body->iterator() as $string) {
                echo $string;
            }
        } else {
            echo (string) $body;
        }
    }

    /**
     * @deprecated
     */
    public function setStatus($status)
    {
        return $this->withStatus($status);
    }

    /**
     * @deprecated
     */
    public function getStatus()
    {
        return $this->getStatusCode();
    }

    /**
     * @deprecated
     */
    public function setHeader($key, $value)
    {
        return $this->withHeader($key, $value);
    }

    /**
     * @deprecated
     */
    public function setCookie($name, $value, $expire = 0, $path = '/', $domain = null, $secure = null, $httponly = true)
    {
        return $this->withCookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * @deprecated
     */
    public function setBody($body)
    {
        return $this->write($body);
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
}

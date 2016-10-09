<?php
namespace Owl\Http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    public static $standard_port = [
        'ftp'   => 21,
        'ssh'   => 22,
        'smtp'  => 25,
        'http'  => 80,
        'pop3'  => 110,
        'https' => 443,
    ];

    protected $scheme;
    protected $host;
    protected $port;
    protected $user;
    protected $pass;
    protected $path;
    protected $query;
    protected $fragment;

    public function __construct($uri = '')
    {
        $parsed = array_merge([
            'scheme'   => '',
            'host'     => '',
            'port'     => null,
            'user'     => '',
            'pass'     => '',
            'path'     => '',
            'query'    => '',
            'fragment' => '',
        ], parse_url($uri));

        parse_str($parsed['query'], $parsed['query']);

        foreach ($parsed as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getScheme()
    {
        return $this->scheme;
    }

    public function getAuthority()
    {
        if (!$authority = $this->getHost()) {
            return '';
        }

        if ($user_info = $this->getUserInfo()) {
            $authority = $user_info.'@'.$authority;
        }

        if ($port = $this->getPort()) {
            $authority = $authority.':'.$port;
        }

        return $authority;
    }

    public function getUserInfo()
    {
        $user_info = $this->user;

        if ($user_info !== '' && $this->pass) {
            $user_info .= ':'.$this->pass;
        }

        return $user_info;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPort()
    {
        $port = $this->port;
        if ($port === null) {
            return;
        }

        $scheme = $this->getScheme();

        if (isset(self::$standard_port[$scheme]) && $port === self::$standard_port[$scheme]) {
            return null;
        }

        return $port;
    }

    public function getPath()
    {
        return $this->path ?: '/';
    }

    public function getExtension()
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function getQuery()
    {
        $query = '';

        if ($this->query) {
            $query = http_build_query($this->query, '', '&', PHP_QUERY_RFC3986);
        }

        return $query;
    }

    public function getFragment()
    {
        return $this->fragment;
    }

    public function withScheme($scheme)
    {
        $uri         = clone $this;
        $uri->scheme = $scheme;

        return $uri;
    }

    public function withoutScheme()
    {
        return $this->withScheme('');
    }

    public function withUserInfo($user, $password = null)
    {
        $uri       = clone $this;
        $uri->user = $user;
        $uri->pass = $password;

        return $uri;
    }

    public function withoutUserInfo()
    {
        return $this->withUserInfo('', '');
    }

    public function withHost($host)
    {
        $uri       = clone $this;
        $uri->host = $host;

        return $uri;
    }

    public function withoutHost()
    {
        return $this->withHost('');
    }

    public function withPort($port)
    {
        $uri       = clone $this;
        $uri->port = ($port === null ? null : (int) $port);

        return $uri;
    }

    public function withoutPort()
    {
        return $this->withPort(null);
    }

    public function withPath($path)
    {
        $uri       = clone $this;
        $uri->path = $path;

        return $uri;
    }

    public function withoutPath()
    {
        return $this->withPath('');
    }

    public function withQuery($query)
    {
        if (is_string($query)) {
            parse_str($query, $query);
        }

        $uri        = clone $this;
        $uri->query = $query ?: [];

        return $uri;
    }

    public function addQuery(array $query)
    {
        $query = array_merge($this->query, $query);

        $uri        = clone $this;
        $uri->query = $query;

        return $uri;
    }

    /**
     * @example
     * $uri->withoutQuery();                // without all
     * $uri->withoutQuery(['foo', 'bar']);
     * $uri->withoutQuery('foo', 'bar');
     */
    public function withoutQuery($keys = null)
    {
        $query = $this->query;

        if (!$keys) {
            $query = [];
        } else {
            $keys = is_array($keys)
            ? $keys
            : func_get_args();

            foreach ($keys as $key) {
                unset($query[$key]);
            }
        }

        $uri        = clone $this;
        $uri->query = $query;

        return $uri;
    }

    public function withFragment($fragment)
    {
        if (!is_string($fragment)) {
            throw new \InvalidArgumentException('Invalid URI fragment');
        }

        $uri           = clone $this;
        $uri->fragment = $fragment;

        return $uri;
    }

    public function withoutFragment()
    {
        return $this->withFragment('');
    }

    public function __toString()
    {
        $uri = '';

        if ($scheme = $this->getScheme()) {
            $uri = $scheme.':';
        }

        if ($authority = $this->getAuthority()) {
            $uri .= '//'.$authority;
        } else {
            $uri = '';
        }

        $uri .= $this->getPath();

        if ($query = $this->getQuery()) {
            $uri .= '?'.$query;
        }

        $fragment = $this->getFragment();
        if ($fragment !== '') {
            $uri .= '#'.$fragment;
        }

        return $uri;
    }
}

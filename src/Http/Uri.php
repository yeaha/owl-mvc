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
    protected $password;
    protected $path;
    protected $query;
    protected $fragment;

    public function __construct($uri = '')
    {
        $parsed = [];
        if ($uri) {
            $parsed = parse_url($uri) ?: [];
        }

        $this->scheme   = isset($parsed['scheme']) ? $parsed['scheme'] : '';
        $this->host     = isset($parsed['host']) ? $parsed['host'] : '';
        $this->port     = isset($parsed['port']) ? $parsed['port'] : null;
        $this->user     = isset($parsed['user']) ? $parsed['user'] : '';
        $this->password = isset($parsed['pass']) ? $parsed['pass'] : '';
        $this->path     = isset($parsed['path']) ? $parsed['path'] : '/';
        $this->fragment = isset($parsed['fragment']) ? $parsed['fragment'] : '';

        $query = [];
        if (isset($parsed['query']) && $parsed['query']) {
            parse_str($parsed['query'], $query);
        }
        $this->query = $query;
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

        if ($user_info !== '' && $this->password) {
            $user_info .= ':'.$this->password;
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
        return $this->path;
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
        $uri           = clone $this;
        $uri->user     = $user;
        $uri->password = $password;

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
        $uri->path = $path ?: '/';

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

    public function withoutQuery(array $keys = [])
    {
        $query = $this->query;

        if (!$keys) {
            $query = [];
        } else {
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

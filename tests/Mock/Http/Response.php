<?php
declare(strict_types=1);

namespace Tests\Mock\Http;

class Response extends \Owl\Http\Response
{
    public function __construct()
    {
        $this->cookies = \Tests\Mock\Cookie::getInstance();
    }

    public function withCookie(string $name, $value, int $expire = 0, string $path = '/', string $domain = null, bool $secure = null, bool $httponly = true): \Owl\Http\Response
    {
        call_user_func_array([$this->cookies, 'set'], func_get_args());

        return $this;
    }

    public function getCookies(): array
    {
        return $this->cookies->get();
    }
}

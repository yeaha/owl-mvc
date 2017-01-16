<?php
declare(strict_types=1);

namespace Owl\Context;

class Session extends \Owl\Context
{
    public function set(string $key, $val)
    {
        $token = $this->getToken();

        $_SESSION[$token][$key] = $val;
    }

    public function get(string $key = '')
    {
        $token = $this->getToken();
        $context = $_SESSION[$token] ?? [];

        return ($key === null)
             ? $context
             : $context[$key] ?? null;
    }

    public function has(string $key): bool
    {
        $token = $this->getToken();

        return isset($_SESSION[$token][$key]);
    }

    public function remove(string $key)
    {
        $token = $this->getToken();

        unset($_SESSION[$token][$key]);
    }

    public function clear()
    {
        $token = $this->getToken();

        unset($_SESSION[$token]);
    }
}

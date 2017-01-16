<?php
declare(strict_types=1);

namespace Owl;

abstract class Context
{
    protected $config;

    abstract public function set(string $key, $val);

    abstract public function get(string $key = '');

    abstract public function has(string $key): bool;

    abstract public function remove(string $key);

    abstract public function clear();

    public function __construct(array $config)
    {
        (new \Owl\Parameter\Validator())->execute($config, [
            'token' => ['type' => 'string'],
        ]);

        $this->config = $config;
    }

    public function setConfig(string $key, $val)
    {
        $this->config[$key] = $val;
    }

    public function getConfig(string $key = '')
    {
        return ($key === '')
             ? $this->config
             : $this->config[$key] ?? null;
    }

    public function getToken(): string
    {
        return $this->getConfig('token');
    }

    // 保存上下文数据，根据需要重载
    public function save()
    {
    }

    public static function factory($type, array $config): Context
    {
        switch (strtolower($type)) {
            case 'session': return new \Owl\Context\Session($config);
            case 'cookie': return new \Owl\Context\Cookie($config);
            case 'redis': return new \Owl\Context\Redis($config);
            default:
                throw new \UnexpectedValueException('Unknown context handler type: ' . $type);
        }
    }
}

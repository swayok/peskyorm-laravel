<?php

declare(strict_types=1);

namespace PeskyORMLaravel;

use Illuminate\Contracts\Foundation\Application as ApplicationContract;
use Illuminate\Foundation\Application;
use PeskyORM\Utils\ServiceContainerInterface;

class OrmServiceContainerAdapter implements ServiceContainerInterface
{
    /**
     * @param Application $laravelApp
     */
    public function __construct(
        protected ApplicationContract $laravelApp
    ) {
    }

    public function get(string $id): mixed
    {
        return $this->laravelApp->get($id);
    }

    public function has(string $id): bool
    {
        return $this->laravelApp->has($id);
    }

    public function instance(
        string $abstract,
        object|string|null $instance = null
    ): static {
        if (!$instance || is_string($instance) || $instance instanceof \Closure) {
            $this->bind($abstract, $instance, true);
        } else {
            $this->laravelApp->instance($abstract, $instance);
        }
        return $this;
    }

    public function bind(
        string $abstract,
        string|\Closure|null $concrete = null,
        bool $singleton = false
    ): static {
        $this->laravelApp->bind($abstract, $concrete, $singleton);
        return $this;
    }

    public function unbind(string $abstract): static
    {
        $this->laravelApp->offsetUnset($abstract);
        return $this;
    }

    public function alias(string $abstract, string $alias): static
    {
        $this->laravelApp->alias($abstract, $alias);
        return $this;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->laravelApp->make($abstract, $parameters);
    }
}
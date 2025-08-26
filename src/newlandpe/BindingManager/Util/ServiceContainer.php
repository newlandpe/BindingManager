<?php

/*
 *
 *  ____  _           _ _             __  __
 * | __ )(_)_ __   __| (_)_ __   __ _|  \/  | __ _ _ __   __ _  __ _  ___ _ __
 * |  _ \| | '_ \ / _` | | '_ \ / _` | |\/| |/ _` | '_ \ / _` |/ _` |/ _ \ '__|
 * | |_) | | | | | (_| | | | | | (_| | |  | | (_| | | | | (_| | (_| |  __/ |
 * |____/|_|_| |_|\__,_|_|_| |_|\__, |_|  |_|\__,_|_| |_|\__,_|\__, |\___|_|
 *                              |___/                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace newlandpe\BindingManager\Util;

use Closure;
use InvalidArgumentException;

class ServiceContainer {

    /** @var array<string, mixed> */
    private array $instances = [];
    /** @var array<string, Closure> */
    private array $factories = [];

    public function get(string $id) {
        if (!$this->has($id)) {
            throw new InvalidArgumentException(sprintf('Service not found: %s', $id));
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $factory = $this->factories[$id];
        $instance = $factory($this);
        $this->instances[$id] = $instance;

        return $instance;
    }

    public function has(string $id): bool {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /**
     * Registers a factory for a service.
     * The factory will be called only the first time the service is requested.
     */
    public function register(string $id, Closure $factory): void {
        if ($this->has($id)) {
            throw new InvalidArgumentException(sprintf("Service '%s' is already registered.", $id));
        }
        $this->factories[$id] = $factory;
    }

    /**
     * Sets a service instance directly.
     */
    public function set(string $id, object $instance): void {
        if ($this->has($id)) {
            throw new InvalidArgumentException(sprintf("Service '%s' is already registered.", $id));
        }
        $this->instances[$id] = $instance;
    }
}

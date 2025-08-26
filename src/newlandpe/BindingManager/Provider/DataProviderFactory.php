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

namespace newlandpe\BindingManager\Provider;

use InvalidArgumentException;

class DataProviderFactory {

    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config, string $dataFolder): DataProviderInterface {
        $providerType = strtolower($config['provider'] ?? 'sqlite');

        switch ($providerType) {
            case 'sqlite':
                $providerClass = SqliteProvider::class;
                $providerConfig = $config['sqlite'] ?? [];
                break;
            case 'mysql':
                $providerClass = MysqlProvider::class;
                $providerConfig = $config['mysql'] ?? [];
                break;
            default:
                throw new InvalidArgumentException("Invalid data provider specified: '$providerType'. Only 'sqlite' and 'mysql' are supported.");
        }

        if (!is_array($providerConfig)) {
            throw new InvalidArgumentException("Configuration for provider '$providerType' must be an array");
        }

        return new $providerClass($providerConfig, $dataFolder);
    }
}

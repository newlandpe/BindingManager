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
use newlandpe\BindingManager\Main;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

class DataProviderFactory {

    public static function create(Main $plugin): DataProviderInterface {
        $config = $plugin->getConfig()->get("database");
        $connector = libasynql::create($plugin, $config, [
            "sqlite" => "sqlite.sql",
            "mysql" => "mysql.sql"
        ]);

        $providerType = strtolower($config['type'] ?? 'sqlite');

        switch ($providerType) {
            case 'sqlite':
                return new SqliteProvider($connector);
            case 'mysql':
                return new MysqlProvider($connector);
            default:
                throw new InvalidArgumentException("Invalid data provider specified: '$providerType'. Only 'sqlite' and 'mysql' are supported.");
        }
    }
}

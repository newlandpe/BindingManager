<?php

declare(strict_types=1);

namespace BindingManager\Provider;

use BindingManager\Util\CodeGenerator;
use InvalidArgumentException;

class DataProviderFactory {

    /**
     * @param array<string, mixed> $config
     */
    public static function create(array $config): DataProviderInterface {
        /** @var string $provider */
        $provider = strtolower((string)($config['provider'] ?? ''));
        /** @var int $codeLengthBytes */
        $codeLengthBytes = (int)($config['code_length_bytes'] ?? 3);
        if ($codeLengthBytes < 1) {
            $codeLengthBytes = 1; // Ensure it's at least 1
        }
        $codeGenerator = new CodeGenerator($codeLengthBytes);

        $timeout = $config['binding_code_timeout_seconds'] ?? 300;

        $providers = [
            'json'   => JsonProvider::class,
            'sqlite' => SqliteProvider::class,
            'mysql'  => MysqlProvider::class,
            'yaml'   => YamlProvider::class,
        ];

        if (!isset($providers[$provider])) {
            throw new InvalidArgumentException("Invalid data provider: $provider");
        }

        $providerClass = $providers[$provider];

        if (!class_exists($providerClass)) {
            throw new InvalidArgumentException("Provider class '$providerClass' does not exist");
        }

        $subConfig = $config[$provider] ?? [];
        if (!is_array($subConfig)) {
            throw new InvalidArgumentException("Configuration for provider '$provider' must be an array");
        }

        $finalConfig = array_merge($subConfig, [
            'binding_code_timeout_seconds' => $timeout
        ]);

        return new $providerClass($finalConfig, $codeGenerator);
    }
}

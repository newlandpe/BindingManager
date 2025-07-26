<?php

declare(strict_types=1);

namespace BindingManager\Provider;

use BindingManager\Util\CodeGenerator;

interface DataProviderInterface {

    /**
     * @param array<string, mixed> $config
     * @param CodeGenerator $codeGenerator
     * @phpstan-param array{binding_code_timeout_seconds?: int} $config
     */
    public function __construct(array $config, CodeGenerator $codeGenerator);

    public function getBindingStatus(int $telegramId): int;

    public function getBoundPlayerName(int $telegramId): ?string;

    public function initiateBinding(string $playerName, int $telegramId): ?string;

    public function confirmBinding(string $playerName, string $code): bool;

    public function unbindByTelegramId(int $telegramId): bool;

    public function isPlayerNameBound(string $playerName): bool;

    public function toggleNotifications(int $telegramId): bool;

    public function areNotificationsEnabled(int $telegramId): bool;

    public function getTelegramIdByPlayerName(string $playerName): ?int;

    public function initiateUnbinding(int $telegramId): ?string;

    public function confirmUnbinding(string $playerName, string $code): bool;

    public function initiateReset(int $telegramId): ?string;
}

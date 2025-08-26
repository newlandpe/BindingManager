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

interface DataProviderInterface {

    public function __construct(array $config, ?string $dataFolder = null);

    public function getBoundPlayerNames(int $telegramId): array;

    public function addPermanentBinding(int $telegramId, string $playerName): bool;

    public function removePermanentBinding(int $telegramId, string $playerName): bool;

    public function isPlayerNameBound(string $playerName): bool;

    public function getTelegramIdByPlayerName(string $playerName): ?int;

    public function createTemporaryBinding(string $playerName, int $telegramId, string $code, int $expiresAt): bool;

    public function findTemporaryBindingByCode(string $code): ?array;

    public function findTemporaryBindingByPlayerName(string $playerName): ?array;

    public function deleteTemporaryBinding(string $code): void;

    public function createTemporaryUnbindCode(int $telegramId, string $playerName, string $code, int $expiresAt): bool;

    public function findTemporaryUnbindCode(string $code): ?array;

    public function deleteTemporaryUnbindCode(string $code): void;

    public function toggleNotifications(string $playerName): bool;

    public function areNotificationsEnabled(string $playerName): bool;

    public function isTwoFactorEnabled(string $playerName): bool;

    public function setTwoFactor(string $playerName, bool $enabled): void;

    public function getTelegramOffset(): int;

    public function setTelegramOffset(int $offset): void;

    public function getUserState(int $userId): ?string;

    public function setUserState(int $userId, ?string $state): void;

    public function deleteExpiredTemporaryBindings(): void;
}

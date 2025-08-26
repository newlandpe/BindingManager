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

use Closure;

interface DataProviderInterface {

    public function getBoundPlayerNames(int $telegramId, Closure $callback): void;

    public function addPermanentBinding(int $telegramId, string $playerName, Closure $callback): void;

    public function removePermanentBinding(int $telegramId, string $playerName, Closure $callback): void;

    public function isPlayerNameBound(string $playerName, Closure $callback): void;

    public function getTelegramIdByPlayerName(string $playerName, Closure $callback): void;

    public function createTemporaryBinding(string $playerName, int $telegramId, string $code, int $expiresAt, Closure $callback): void;

    public function findTemporaryBindingByCode(string $code, Closure $callback): void;

    public function findTemporaryBindingByPlayerName(string $playerName, Closure $callback): void;

    public function deleteTemporaryBinding(string $code): void;

    public function createTemporaryUnbindCode(int $telegramId, string $playerName, string $code, int $expiresAt, Closure $callback): void;

    public function findTemporaryUnbindCode(string $code, Closure $callback): void;

    public function deleteTemporaryUnbindCode(string $code): void;

    public function toggleNotifications(string $playerName, Closure $callback): void;

    public function areNotificationsEnabled(string $playerName, Closure $callback): void;

    public function isTwoFactorEnabled(string $playerName, Closure $callback): void;

    public function setTwoFactor(string $playerName, bool $enabled): void;

    public function getTelegramOffset(Closure $callback): void;

    public function setTelegramOffset(int $offset): void;

    public function getUserState(int $userId, Closure $callback): void;

    public function setUserState(int $userId, ?string $state): void;

    public function deleteExpiredTemporaryBindings(): void;

    public function close(): void;
}

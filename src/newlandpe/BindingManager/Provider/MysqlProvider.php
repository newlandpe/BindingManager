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
use PDO;
use PDOException;

class MysqlProvider implements DataProviderInterface {

    private PDO $pdo;
    private string $bindingsTable;
    private string $codesTable;

    public function __construct(array $config, ?string $dataFolder = null) {
        if (!isset($config['host'], $config['user'], $config['password'], $config['database'])) {
            throw new InvalidArgumentException("Missing MySQL configuration parameters.");
        }

        $this->bindingsTable = isset($config['bindings-table']) ? $config['bindings-table'] : 'bindings';
        $this->codesTable = isset($config['codes-table']) ? $config['codes-table'] : 'temporary_codes';

        $port = isset($config['port']) ? $config['port'] : 3306;
        $dsn = "mysql:host={$config['host']};port={$port};dbname={$config['database']};charset=utf8mb4";
        try {
            $this->pdo = new PDO($dsn, $config['user'], $config['password']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTables();
        } catch (PDOException $e) {
            throw new PDOException("Failed to connect to MySQL: " . $e->getMessage());
        }
    }

    private function createTables(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$this->bindingsTable}` (
            `telegram_id` BIGINT NOT NULL,
            `player_name` VARCHAR(255) NOT NULL,
            `notifications_enabled` BOOLEAN NOT NULL DEFAULT TRUE,
            `two_factor_enabled` BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY (`telegram_id`, `player_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$this->codesTable}` (
            `code` VARCHAR(255) PRIMARY KEY,
            `type` VARCHAR(16) NOT NULL,
            `telegram_id` BIGINT NOT NULL,
            `player_name` VARCHAR(255) NOT NULL,
            `expires_at` INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `meta_data` (
            `key` VARCHAR(255) PRIMARY KEY,
            `value` TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public function getBoundPlayerNames(int $telegramId): array {
        $stmt = $this->pdo->prepare("SELECT player_name FROM {$this->bindingsTable} WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function addPermanentBinding(int $telegramId, string $playerName): bool {
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO {$this->bindingsTable} (telegram_id, player_name) VALUES (?, ?)");
        $stmt->execute([$telegramId, strtolower($playerName)]);
        return $stmt->rowCount() > 0;
    }

    public function removePermanentBinding(int $telegramId, string $playerName): bool {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->bindingsTable} WHERE telegram_id = ? AND player_name = ?");
        $stmt->execute([$telegramId, strtolower($playerName)]);
        return $stmt->rowCount() > 0;
    }

    public function isPlayerNameBound(string $playerName): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->bindingsTable} WHERE player_name = ? LIMIT 1");
        $stmt->execute([strtolower($playerName)]);
        return $stmt->fetchColumn() !== false;
    }

    public function getTelegramIdByPlayerName(string $playerName): ?int {
        $stmt = $this->pdo->prepare("SELECT telegram_id FROM {$this->bindingsTable} WHERE player_name = ?");
        $stmt->execute([strtolower($playerName)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? (int)$result['telegram_id'] : null;
    }

    public function createTemporaryBinding(string $playerName, int $telegramId, string $code, int $expiresAt): bool {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->codesTable} (code, type, telegram_id, player_name, expires_at) VALUES (?, 'bind', ?, ?, ?)");
        $stmt->execute([$code, $telegramId, strtolower($playerName), $expiresAt]);
        return $stmt->rowCount() > 0;
    }

    public function findTemporaryBindingByCode(string $code): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->codesTable} WHERE code = ? AND type = 'bind'");
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    public function findTemporaryBindingByPlayerName(string $playerName): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->codesTable} WHERE player_name = ? AND type = 'bind'");
        $stmt->execute([strtolower($playerName)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    public function deleteTemporaryBinding(string $code): void {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->codesTable} WHERE code = ? AND type = 'bind'");
        $stmt->execute([$code]);
    }

    public function createTemporaryUnbindCode(int $telegramId, string $playerName, string $code, int $expiresAt): bool {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->codesTable} (code, type, telegram_id, player_name, expires_at) VALUES (?, 'unbind', ?, ?, ?)");
        $stmt->execute([$code, $telegramId, strtolower($playerName), $expiresAt]);
        return $stmt->rowCount() > 0;
    }

    public function findTemporaryUnbindCode(string $code): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->codesTable} WHERE code = ? AND type = 'unbind'");
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result : null;
    }

    public function deleteTemporaryUnbindCode(string $code): void {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->codesTable} WHERE code = ? AND type = 'unbind'");
        $stmt->execute([$code]);
    }

    public function toggleNotifications(string $playerName): bool {
        $stmt = $this->pdo->prepare("UPDATE {$this->bindingsTable} SET notifications_enabled = NOT notifications_enabled WHERE player_name = ?");
        $stmt->execute([strtolower($playerName)]);
        return $this->areNotificationsEnabled($playerName);
    }

    public function areNotificationsEnabled(string $playerName): bool {
        $stmt = $this->pdo->prepare("SELECT notifications_enabled FROM {$this->bindingsTable} WHERE player_name = ?");
        $stmt->execute([strtolower($playerName)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? (bool)$result['notifications_enabled'] : true;
    }

    public function isTwoFactorEnabled(string $playerName): bool {
        $stmt = $this->pdo->prepare("SELECT two_factor_enabled FROM {$this->bindingsTable} WHERE player_name = ?");
        $stmt->execute([strtolower($playerName)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? (bool)$result['two_factor_enabled'] : false;
    }

    public function setTwoFactor(string $playerName, bool $enabled): void {
        $stmt = $this->pdo->prepare("UPDATE {$this->bindingsTable} SET two_factor_enabled = ? WHERE player_name = ?");
        $stmt->execute([$enabled, strtolower($playerName)]);
    }

    public function getTelegramOffset(): int {
        $stmt = $this->pdo->prepare("SELECT value FROM meta_data WHERE `key` = 'telegram_offset'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? (int)$result['value'] : 0;
    }

    public function setTelegramOffset(int $offset): void {
        $stmt = $this->pdo->prepare("INSERT INTO meta_data (`key`, `value`) VALUES ('telegram_offset', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $stmt->execute([$offset]);
    }

    public function getUserState(int $userId): ?string {
        $stmt = $this->pdo->prepare("SELECT value FROM meta_data WHERE `key` = ?");
        $stmt->execute(['user_state_' . $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result !== false ? $result['value'] : null;
    }

    public function setUserState(int $userId, ?string $state): void {
        if ($state === null) {
            $stmt = $this->pdo->prepare("DELETE FROM meta_data WHERE `key` = ?");
            $stmt->execute(['user_state_' . $userId]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO meta_data (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $stmt->execute(['user_state_' . $userId, $state]);
        }
    }

    public function deleteExpiredTemporaryBindings(): void {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->codesTable} WHERE expires_at < ?");
        $stmt->execute([time()]);
    }
}

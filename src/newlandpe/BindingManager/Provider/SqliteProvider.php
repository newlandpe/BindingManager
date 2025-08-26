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

use SQLite3;

class SqliteProvider implements DataProviderInterface {

    private SQLite3 $db;
    private string $bindingsTable;
    private string $codesTable;

    public function __construct(array $config, ?string $dataFolder = null) {
        $this->bindingsTable = $config['bindings-table'] ?? 'bindings';
        $this->codesTable = $config['codes-table'] ?? 'temporary_codes';

        $filePath = $dataFolder !== null ? $dataFolder . ($config['file'] ?? 'bindings.sqlite') : 'bindings.sqlite';
        $this->db = new SQLite3($filePath);
        $this->db->exec("PRAGMA journal_mode = WAL;");
        $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->bindingsTable} (
            telegram_id INTEGER NOT NULL,
            player_name TEXT NOT NULL,
            notifications_enabled INTEGER NOT NULL DEFAULT 1,
            two_factor_enabled INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (telegram_id, player_name)
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS {$this->codesTable} (
            code TEXT PRIMARY KEY,
            type TEXT NOT NULL,
            telegram_id INTEGER NOT NULL,
            player_name TEXT NOT NULL,
            expires_at INTEGER NOT NULL
        )");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_temp_telegram_id ON {$this->codesTable} (telegram_id);");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_temp_player_name ON {$this->codesTable} (player_name);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS meta_data (key TEXT PRIMARY KEY, value TEXT);");
    }

    public function getBoundPlayerNames(int $telegramId): array {
        $stmt = $this->db->prepare("SELECT player_name FROM {$this->bindingsTable} WHERE telegram_id = :id");
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $names = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $names[] = $row['player_name'];
        }
        return $names;
    }

    public function addPermanentBinding(int $telegramId, string $playerName): bool {
        $stmt = $this->db->prepare("INSERT OR IGNORE INTO {$this->bindingsTable} (telegram_id, player_name) VALUES (:tid, :pname)");
        $stmt->bindValue(':tid', $telegramId, SQLITE3_INTEGER);
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function removePermanentBinding(int $telegramId, string $playerName): bool {
        $stmt = $this->db->prepare("DELETE FROM {$this->bindingsTable} WHERE telegram_id = :tid AND player_name = :pname");
        $stmt->bindValue(':tid', $telegramId, SQLITE3_INTEGER);
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function isPlayerNameBound(string $playerName): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM {$this->bindingsTable} WHERE player_name = :pname LIMIT 1");
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray() !== false;
    }

    public function getTelegramIdByPlayerName(string $playerName): ?int {
        $stmt = $this->db->prepare("SELECT telegram_id FROM {$this->bindingsTable} WHERE player_name = :pname");
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? (int)$data['telegram_id'] : null;
    }

    public function createTemporaryBinding(string $playerName, int $telegramId, string $code, int $expiresAt): bool {
        $stmt = $this->db->prepare("INSERT INTO {$this->codesTable} (code, type, telegram_id, player_name, expires_at) VALUES (:code, 'bind', :tid, :pname, :exp)");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->bindValue(':tid', $telegramId, SQLITE3_INTEGER);
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $stmt->bindValue(':exp', $expiresAt, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function findTemporaryBindingByCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->codesTable} WHERE code = :code AND type = 'bind'");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? $data : null;
    }

    public function findTemporaryBindingByPlayerName(string $playerName): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->codesTable} WHERE player_name = :pname AND type = 'bind'");
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? $data : null;
    }

    public function deleteTemporaryBinding(string $code): void {
        $stmt = $this->db->prepare("DELETE FROM {$this->codesTable} WHERE code = :code AND type = 'bind'");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function createTemporaryUnbindCode(int $telegramId, string $playerName, string $code, int $expiresAt): bool {
        $stmt = $this->db->prepare("INSERT INTO {$this->codesTable} (code, type, telegram_id, player_name, expires_at) VALUES (:code, 'unbind', :tid, :pname, :exp)");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->bindValue(':tid', $telegramId, SQLITE3_INTEGER);
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $stmt->bindValue(':exp', $expiresAt, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function findTemporaryUnbindCode(string $code): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->codesTable} WHERE code = :code AND type = 'unbind'");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? $data : null;
    }

    public function deleteTemporaryUnbindCode(string $code): void {
        $stmt = $this->db->prepare("DELETE FROM {$this->codesTable} WHERE code = :code AND type = 'unbind'");
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function toggleNotifications(string $playerName): bool {
        $stmt = $this->db->prepare("UPDATE {$this->bindingsTable} SET notifications_enabled = 1 - notifications_enabled WHERE player_name = :pname");
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $stmt->execute();
        return $this->areNotificationsEnabled($playerName);
    }

    public function areNotificationsEnabled(string $playerName): bool {
        $stmt = $this->db->prepare("SELECT notifications_enabled FROM {$this->bindingsTable} WHERE player_name = :pname");
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? (bool)$data['notifications_enabled'] : true; // Default to true if not found
    }

    public function isTwoFactorEnabled(string $playerName): bool {
        $stmt = $this->db->prepare("SELECT two_factor_enabled FROM {$this->bindingsTable} WHERE player_name = :pname");
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? (bool)$data['two_factor_enabled'] : false; // Default to false
    }

    public function setTwoFactor(string $playerName, bool $enabled): void {
        $stmt = $this->db->prepare("UPDATE {$this->bindingsTable} SET two_factor_enabled = :enabled WHERE player_name = :pname");
        $stmt->bindValue(':enabled', $enabled ? 1 : 0, SQLITE3_INTEGER);
        $stmt->bindValue(':pname', strtolower($playerName), SQLITE3_TEXT);
        $stmt->execute();
    }

    public function getTelegramOffset(): int {
        $stmt = $this->db->prepare("SELECT value FROM meta_data WHERE key = 'telegram_offset'");
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? (int)$data['value'] : 0;
    }

    public function setTelegramOffset(int $offset): void {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO meta_data (key, value) VALUES ('telegram_offset', :offset)");
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $stmt->execute();
    }

    public function getUserState(int $userId): ?string {
        $stmt = $this->db->prepare("SELECT value FROM meta_data WHERE key = :key");
        $stmt->bindValue(':key', 'user_state_' . $userId, SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        return $data !== false ? $data['value'] : null;
    }

    public function setUserState(int $userId, ?string $state): void {
        if ($state === null) {
            $stmt = $this->db->prepare("DELETE FROM meta_data WHERE key = :key");
            $stmt->bindValue(':key', 'user_state_' . $userId, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("INSERT OR REPLACE INTO meta_data (key, value) VALUES (:key, :value)");
            $stmt->bindValue(':key', 'user_state_' . $userId, SQLITE3_TEXT);
            $stmt->bindValue(':value', $state, SQLITE3_TEXT);
            $stmt->execute();
        }
    }

    public function deleteExpiredTemporaryBindings(): void {
        $stmt = $this->db->prepare("DELETE FROM {$this->codesTable} WHERE expires_at < :time");
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
    }
}

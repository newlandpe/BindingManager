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
use poggit\libasynql\DataConnector;

abstract class AbstractDataProvider implements DataProviderInterface {

    protected DataConnector $db;

    public function __construct(DataConnector $db) {
        $this->db = $db;
    }

    public function getBoundPlayerNames(int $telegramId, Closure $callback): void {
        $this->db->executeSelect("binding.get_bound_player_names", ["telegram_id" => $telegramId], function (array $rows) use ($callback): void {
            $names = [];
            foreach ($rows as $row) {
                $names[] = $row["player_name"];
            }
            $callback($names);
        });
    }

    public function addPermanentBinding(int $telegramId, string $playerName, Closure $callback): void {
        $this->db->executeChange("binding.add_permanent_binding", ["telegram_id" => $telegramId, "player_name" => strtolower($playerName)], function (int $changes) use ($callback): void {
            $callback($changes > 0);
        });
    }

    public function removePermanentBinding(int $telegramId, string $playerName, Closure $callback): void {
        $this->db->executeChange("binding.remove_permanent_binding", ["telegram_id" => $telegramId, "player_name" => strtolower($playerName)], function (int $changes) use ($callback): void {
            $callback($changes > 0);
        });
    }

    public function isPlayerNameBound(string $playerName, Closure $callback): void {
        $this->db->executeSelect("binding.is_player_name_bound", ["player_name" => strtolower($playerName)], function (array $rows) use ($callback): void {
            $callback(count($rows) > 0);
        });
    }

    public function getTelegramIdByPlayerName(string $playerName, Closure $callback): void {
        $this->db->executeSelect("binding.get_telegram_id_by_player_name", ["player_name" => strtolower($playerName)], function (array $rows) use ($callback): void {
            $callback($rows[0]["telegram_id"] ?? null);
        });
    }

    public function createTemporaryBinding(string $playerName, int $telegramId, string $code, int $expiresAt, Closure $callback): void {
        $this->db->executeChange("binding.create_temporary_binding", ["player_name" => strtolower($playerName), "telegram_id" => $telegramId, "code" => $code, "expires_at" => $expiresAt], function (int $changes) use ($callback): void {
            $callback($changes > 0);
        });
    }

    public function findTemporaryBindingByCode(string $code, Closure $callback): void {
        $this->db->executeSelect("binding.find_temporary_binding_by_code", ["code" => $code], function (array $rows) use ($callback): void {
            $callback($rows[0] ?? null);
        });
    }

    public function findTemporaryBindingByPlayerName(string $playerName, Closure $callback): void {
        $this->db->executeSelect("binding.find_temporary_binding_by_player_name", ["player_name" => strtolower($playerName)], function (array $rows) use ($callback): void {
            $callback($rows[0] ?? null);
        });
    }

    public function deleteTemporaryBinding(string $code): void {
        $this->db->executeGeneric("binding.delete_temporary_binding", ["code" => $code]);
    }

    public function createTemporaryUnbindCode(int $telegramId, string $playerName, string $code, int $expiresAt, Closure $callback): void {
        $this->db->executeChange("binding.create_temporary_unbind_code", ["telegram_id" => $telegramId, "player_name" => strtolower($playerName), "code" => $code, "expires_at" => $expiresAt], function (int $changes) use ($callback): void {
            $callback($changes > 0);
        });
    }

    public function findTemporaryUnbindCode(string $code, Closure $callback): void {
        $this->db->executeSelect("binding.find_temporary_unbind_code", ["code" => $code], function (array $rows) use ($callback): void {
            $callback($rows[0] ?? null);
        });
    }

    public function deleteTemporaryUnbindCode(string $code): void {
        $this->db->executeGeneric("binding.delete_temporary_unbind_code", ["code" => $code]);
    }

    public function toggleNotifications(string $playerName, Closure $callback): void {
        $this->db->executeChange("binding.toggle_notifications", ["player_name" => strtolower($playerName)], function (int $changes) use ($callback, $playerName): void {
            $this->areNotificationsEnabled($playerName, $callback);
        });
    }

    public function areNotificationsEnabled(string $playerName, Closure $callback): void {
        $this->db->executeSelect("binding.are_notifications_enabled", ["player_name" => strtolower($playerName)], function (array $rows) use ($callback): void {
            $callback($rows[0]["notifications_enabled"] ?? true);
        });
    }

    public function isTwoFactorEnabled(string $playerName, Closure $callback): void {
        $this->db->executeSelect("binding.is_two_factor_enabled", ["player_name" => strtolower($playerName)], function (array $rows) use ($callback): void {
            $callback($rows[0]["two_factor_enabled"] ?? false);
        });
    }

    public function setTwoFactor(string $playerName, bool $enabled): void {
        $this->db->executeGeneric("binding.set_two_factor", ["player_name" => strtolower($playerName), "enabled" => (int)$enabled]);
    }

    public function getTelegramOffset(Closure $callback): void {
        $this->db->executeSelect("binding.get_telegram_offset", [], function (array $rows) use ($callback): void {
            $callback($rows[0]["value"] ?? 0);
        });
    }

    public function setTelegramOffset(int $offset): void {
        $this->db->executeGeneric("binding.set_telegram_offset", ["offset" => $offset]);
    }

    public function getUserState(int $userId, Closure $callback): void {
        $this->db->executeSelect("binding.get_user_state", ["key" => "user_state_" . $userId], function (array $rows) use ($callback): void {
            $callback($rows[0]["value"] ?? null);
        });
    }

    public function setUserState(int $userId, ?string $state): void {
        if ($state === null) {
            $this->db->executeGeneric("binding.delete_user_state", ["key" => "user_state_" . $userId]);
        } else {
            $this->db->executeGeneric("binding.set_user_state", ["key" => "user_state_" . $userId, "value" => $state]);
        }
    }

    public function deleteExpiredTemporaryBindings(): void {
        $this->db->executeGeneric("binding.delete_expired_temporary_bindings", ["time" => time()]);
    }

    public function close(): void {
        $this->db->close();
    }
}

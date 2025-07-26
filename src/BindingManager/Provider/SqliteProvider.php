<?php

declare(strict_types=1);

namespace BindingManager\Provider;

use BindingManager\Main;
use BindingManager\Util\CodeGenerator;
use SQLite3;

class SqliteProvider implements DataProviderInterface {

    private SQLite3 $db;
    private CodeGenerator $codeGenerator;
    private int $bindingCodeTimeoutSeconds;

    /**
     * @param array<string, mixed> $config
     * @param CodeGenerator $codeGenerator
     */
    public function __construct(array $config, CodeGenerator $codeGenerator) {
        $main = Main::getInstance();
        if ($main === null) {
            throw new \RuntimeException('Main instance not available.');
        }
        $filePath = $main->getDataFolder() . ($config['file'] ?? 'bindings.sqlite');
        $this->db = new SQLite3($filePath);
        $this->codeGenerator = $codeGenerator;
        /** @var int $timeout */
        $this->bindingCodeTimeoutSeconds = (int)($config['binding_code_timeout_seconds'] ?? 300);
        $this->db->exec("CREATE TABLE IF NOT EXISTS bindings (
            telegram_id INTEGER PRIMARY KEY,
            player_name TEXT NOT NULL,
            confirmed INTEGER NOT NULL DEFAULT 0,
            code TEXT,
            timestamp INTEGER,
            notifications_enabled INTEGER NOT NULL DEFAULT 1,
            unbind_code TEXT,
            unbind_timestamp INTEGER
        )");
    }

    public function getBindingStatus(int $telegramId): int {
        $stmt = $this->db->prepare("SELECT confirmed, timestamp FROM bindings WHERE telegram_id = :id");
        if ($stmt === false) return 0;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) return 0;
        $fetch = $result->fetchArray(SQLITE3_ASSOC);
        if (!is_array($fetch)) return 0;

        if ((bool)($fetch['confirmed'] ?? false)) {
            return 2; // Confirmed
        }

        // If pending, check if the code has expired
        if (isset($fetch['timestamp'])) {
            if (time() - (int)$fetch['timestamp'] > $this->bindingCodeTimeoutSeconds) {
                // Code expired, remove the pending binding
                $this->unbindByTelegramId($telegramId);
                return 0; // Treat as not bound
            }
        }
        return 1; // Pending
    }

    public function getBoundPlayerName(int $telegramId): ?string {
        $stmt = $this->db->prepare("SELECT player_name FROM bindings WHERE telegram_id = :id");
        if ($stmt === false) return null;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) return null;
        $fetch = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($fetch) ? ($fetch['player_name'] ?? null) : null;
    }

    public function initiateBinding(string $playerName, int $telegramId): ?string {
        if ($this->getBindingStatus($telegramId) !== 0) {
            return null; // Already bound or pending
        }
        if ($this->isPlayerNameBound($playerName)) {
            return null; // Player name already taken
        }

        $code = $this->codeGenerator->generate();
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO bindings (telegram_id, player_name, code, timestamp) VALUES (:id, :name, :code, :time)");
        if ($stmt === false) return null;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $stmt->bindValue(':name', strtolower($playerName), SQLITE3_TEXT);
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $stmt->execute();
        return $code;
    }

    public function confirmBinding(string $playerName, string $code): bool {
        $stmt = $this->db->prepare("SELECT telegram_id, timestamp FROM bindings WHERE player_name = :name AND code = :code AND confirmed = 0");
        if ($stmt === false) return false;
        $stmt->bindValue(':name', $playerName, SQLITE3_TEXT);
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return false;
        $data = $result->fetchArray(SQLITE3_ASSOC);

        if (!is_array($data) || (time() - (int)($data['timestamp'] ?? 0) > $this->bindingCodeTimeoutSeconds)) {
            return false; // Not found or expired
        }

        $updateStmt = $this->db->prepare("UPDATE bindings SET confirmed = 1, code = NULL, timestamp = NULL WHERE player_name = :name");
        if ($updateStmt === false) return false;
        $updateStmt->bindValue(':name', $playerName, SQLITE3_TEXT);
        $updateStmt->execute();
        return $this->db->changes() > 0;
    }

    public function unbindByTelegramId(int $telegramId): bool {
        $stmt = $this->db->prepare("DELETE FROM bindings WHERE telegram_id = :id");
        if ($stmt === false) return false;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->db->changes() > 0;
    }

    public function isPlayerNameBound(string $playerName): bool {
        $stmt = $this->db->prepare("SELECT 1 FROM bindings WHERE player_name = :name AND confirmed = 1");
        if ($stmt === false) return false;
        $stmt->bindValue(':name', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return false;
        return is_array($result->fetchArray());
    }

    public function toggleNotifications(int $telegramId): bool {
        $stmt = $this->db->prepare("UPDATE bindings SET notifications_enabled = 1 - notifications_enabled WHERE telegram_id = :id");
        if ($stmt === false) return false;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->areNotificationsEnabled($telegramId);
    }

    public function areNotificationsEnabled(int $telegramId): bool {
        $stmt = $this->db->prepare("SELECT notifications_enabled FROM bindings WHERE telegram_id = :id");
        if ($stmt === false) return false;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) return false;
        $fetch = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($fetch) && (($fetch['notifications_enabled'] ?? 0) == 1);
    }

    public function getTelegramIdByPlayerName(string $playerName): ?int {
        $stmt = $this->db->prepare("SELECT telegram_id FROM bindings WHERE player_name = :name AND confirmed = 1");
        if ($stmt === false) return null;
        $stmt->bindValue(':name', strtolower($playerName), SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return null;
        $fetch = $result->fetchArray(SQLITE3_ASSOC);
        return is_array($fetch) ? (int)($fetch['telegram_id'] ?? 0) : null;
    }

    public function initiateUnbinding(int $telegramId): ?string {
        $stmt = $this->db->prepare("SELECT confirmed FROM bindings WHERE telegram_id = :id");
        if ($stmt === false) return null;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) return null;
        $fetch = $result->fetchArray(SQLITE3_ASSOC);
        if (!is_array($fetch) || !($fetch['confirmed'] ?? false)) {
            return null; // Not bound, cannot initiate unbinding
        }

        $code = $this->codeGenerator->generate();
        $updateStmt = $this->db->prepare("UPDATE bindings SET unbind_code = :code, unbind_timestamp = :time WHERE telegram_id = :id");
        if ($updateStmt === false) return null;
        $updateStmt->bindValue(':code', $code, SQLITE3_TEXT);
        $updateStmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $updateStmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $updateStmt->execute();
        return $code;
    }

    public function confirmUnbinding(string $playerName, string $code): bool {
        $stmt = $this->db->prepare("SELECT telegram_id, unbind_timestamp FROM bindings WHERE player_name = :name AND unbind_code = :code AND confirmed = 1");
        if ($stmt === false) return false;
        $stmt->bindValue(':name', $playerName, SQLITE3_TEXT);
        $stmt->bindValue(':code', $code, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) return false;
        $data = $result->fetchArray(SQLITE3_ASSOC);

        if (!is_array($data) || (isset($data['unbind_timestamp']) && (time() - (int)$data['unbind_timestamp'] > $this->bindingCodeTimeoutSeconds))) {
            // Code expired or not found, clear unbind request
            $updateStmt = $this->db->prepare("UPDATE bindings SET unbind_code = NULL, unbind_timestamp = NULL WHERE player_name = :name");
            if ($updateStmt === false) return false;
            $updateStmt->bindValue(':name', $playerName, SQLITE3_TEXT);
            $updateStmt->execute();
            return false;
        }

        // Code is valid, perform unbinding
        return $this->unbindByTelegramId((int)($data['telegram_id'] ?? 0));
    }

    public function initiateReset(int $telegramId): ?string {
        $stmt = $this->db->prepare("SELECT confirmed FROM bindings WHERE telegram_id = :id");
        if ($stmt === false) return null;
        $stmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        if ($result === false) return null;
        $fetch = $result->fetchArray(SQLITE3_ASSOC);
        if (!is_array($fetch) || !($fetch['confirmed'] ?? false)) {
            return null; // Not bound, cannot initiate reset
        }

        $code = $this->codeGenerator->generate();
        $updateStmt = $this->db->prepare("UPDATE bindings SET reset_code = :code, reset_timestamp = :time WHERE telegram_id = :id");
        if ($updateStmt === false) return null;
        $updateStmt->bindValue(':code', $code, SQLITE3_TEXT);
        $updateStmt->bindValue(':time', time(), SQLITE3_INTEGER);
        $updateStmt->bindValue(':id', $telegramId, SQLITE3_INTEGER);
        $updateStmt->execute();
        return $code;
    }
}

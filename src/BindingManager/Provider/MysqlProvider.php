<?php

declare(strict_types=1);

namespace BindingManager\Provider;

use BindingManager\Util\CodeGenerator;
use InvalidArgumentException;
use PDO;
use PDOException;

class MysqlProvider implements DataProviderInterface {

    private PDO $pdo;
    private string $table;
    private CodeGenerator $codeGenerator;
    private int $bindingCodeTimeoutSeconds;

    /**
     * @param array<string, mixed> $config
     * @param CodeGenerator $codeGenerator
     */
    public function __construct(array $config, CodeGenerator $codeGenerator) {
        if (!isset($config['host'], $config['user'], $config['password'], $config['database'], $config['table'])) {
            throw new InvalidArgumentException("Missing MySQL configuration parameters (host, user, password, database, table).");
        }

        $host = $config['host'];
        if (!is_string($host)) {
            throw new InvalidArgumentException("Invalid host type.");
        }
        $user = $config['user'];
        if (!is_string($user)) {
            throw new InvalidArgumentException("Invalid user type.");
        }
        $password = $config['password'];
        if (!is_string($password)) {
            throw new InvalidArgumentException("Invalid password type.");
        }
        $database = $config['database'];
        if (!is_string($database)) {
            throw new InvalidArgumentException("Invalid database type.");
        }
        $table = $config['table'];
        if (!is_string($table)) {
            throw new InvalidArgumentException("Invalid table type.");
        }
        $this->table = $table;
        $this->codeGenerator = $codeGenerator;
        $this->bindingCodeTimeoutSeconds = (int)($config['binding_code_timeout_seconds'] ?? 300);

        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $user, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTable();
        } catch (PDOException $e) {
            throw new PDOException("Failed to connect to MySQL: " . $e->getMessage());
        }
    }

    private function createTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `telegram_id` BIGINT NOT NULL PRIMARY KEY,
            `player_name` VARCHAR(255) NOT NULL UNIQUE,
            `confirmed` BOOLEAN NOT NULL DEFAULT FALSE,
            `code` VARCHAR(12) NULL,
            `timestamp` INT NULL,
            `notifications_enabled` BOOLEAN NOT NULL DEFAULT TRUE,
            `unbind_code` VARCHAR(12) NULL,
            `unbind_timestamp` INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }

    public function getBindingStatus(int $telegramId): int {
        $stmt = $this->pdo->prepare("SELECT confirmed, timestamp FROM `{$this->table}` WHERE telegram_id = :telegram_id");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result)) {
            return 0; // Not bound
        }

        if ((bool)($result['confirmed'] ?? false)) {
            return 2; // Confirmed
        }

        // If pending, check if the code has expired
        if (isset($result['timestamp'])) {
            if (time() - (int)$result['timestamp'] > $this->bindingCodeTimeoutSeconds) {
                // Code expired, remove the pending binding
                $this->unbindByTelegramId($telegramId);
                return 0; // Treat as not bound
            }
        }
        return 1; // Pending
    }

    public function getBoundPlayerName(int $telegramId): ?string {
        $stmt = $this->pdo->prepare("SELECT player_name FROM `{$this->table}` WHERE telegram_id = :telegram_id AND confirmed = 1");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result === false || !is_array($result)) return null;
        return (isset($result['player_name']) && is_string($result['player_name'])) ? $result['player_name'] : null;
    }

    public function initiateBinding(string $playerName, int $telegramId): ?string {
        if ($this->getBindingStatus($telegramId) !== 0) {
            return null; // Already bound or pending
        }
        if ($this->isPlayerNameBound($playerName)) {
            return null; // Player name already taken
        }

        $code = $this->codeGenerator->generate();
        $stmt = $this->pdo->prepare("INSERT INTO `{$this->table}` (player_name, telegram_id, code, timestamp) VALUES (:player_name, :telegram_id, :code, :timestamp) ON DUPLICATE KEY UPDATE code = :code, timestamp = :timestamp, confirmed = 0");
        $stmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->bindParam(":code", $code, PDO::PARAM_STR);
        $stmt->bindValue(":timestamp", time(), PDO::PARAM_INT);
        $stmt->execute();
        return $code;
    }

    public function confirmBinding(string $playerName, string $code): bool {
        $stmt = $this->pdo->prepare("SELECT timestamp FROM `{$this->table}` WHERE player_name = :player_name AND code = :code AND confirmed = 0");
        $stmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
        $stmt->bindParam(":code", $code, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result) || (time() - (int)($result['timestamp'] ?? 0) > $this->bindingCodeTimeoutSeconds)) {
            return false; // Not found or expired
        }

        $updateStmt = $this->pdo->prepare("UPDATE `{$this->table}` SET confirmed = 1, code = NULL, timestamp = NULL WHERE player_name = :player_name");
        $updateStmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
        $updateStmt->execute();
        return $updateStmt->rowCount() > 0;
    }

    public function unbindByTelegramId(int $telegramId): bool {
        $stmt = $this->pdo->prepare("DELETE FROM `{$this->table}` WHERE telegram_id = :telegram_id");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function isPlayerNameBound(string $playerName): bool {
        $stmt = $this->pdo->prepare("SELECT 1 FROM `{$this->table}` WHERE player_name = :player_name AND confirmed = 1");
        $stmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn() !== false && $stmt->fetchColumn() !== null;
    }

    public function toggleNotifications(int $telegramId): bool {
        $stmt = $this->pdo->prepare("UPDATE `{$this->table}` SET notifications_enabled = NOT notifications_enabled WHERE telegram_id = :telegram_id");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    public function areNotificationsEnabled(int $telegramId): bool {
        $stmt = $this->pdo->prepare("SELECT notifications_enabled FROM `{$this->table}` WHERE telegram_id = :telegram_id");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) && (bool)($result['notifications_enabled'] ?? false);
    }

    public function getTelegramIdByPlayerName(string $playerName): ?int {
        $stmt = $this->pdo->prepare("SELECT telegram_id FROM `{$this->table}` WHERE player_name = :player_name AND confirmed = 1");
        $stmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($result) && (isset($result['telegram_id']) && is_numeric($result['telegram_id'])) ? (int)$result['telegram_id'] : null;
    }

    public function initiateUnbinding(int $telegramId): ?string {
        $stmt = $this->pdo->prepare("SELECT confirmed FROM `{$this->table}` WHERE telegram_id = :telegram_id");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($result) || !($result['confirmed'] ?? false)) {
            return null; // Not bound, cannot initiate unbinding
        }

        $code = $this->codeGenerator->generate();
        $updateStmt = $this->pdo->prepare("UPDATE `{$this->table}` SET unbind_code = :code, unbind_timestamp = :time WHERE telegram_id = :telegram_id");
        $updateStmt->bindParam(":code", $code, PDO::PARAM_STR);
        $updateStmt->bindValue(":time", time(), PDO::PARAM_INT);
        $updateStmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $updateStmt->execute();
        return $code;
    }

    public function confirmUnbinding(string $playerName, string $code): bool {
        $stmt = $this->pdo->prepare("SELECT telegram_id, unbind_timestamp FROM `{$this->table}` WHERE player_name = :player_name AND unbind_code = :code AND confirmed = 1");
        $stmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
        $stmt->bindParam(":code", $code, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($result) || (time() - (int)($result['unbind_timestamp'] ?? 0) > $this->bindingCodeTimeoutSeconds)) {
            // Code expired or not found, clear unbind request
            $updateStmt = $this->pdo->prepare("UPDATE `{$this->table}` SET unbind_code = NULL, unbind_timestamp = NULL WHERE player_name = :player_name");
            $updateStmt->bindParam(":player_name", $playerName, PDO::PARAM_STR);
            $updateStmt->execute();
            return false;
        }

        // Code is valid, perform unbinding
        return $this->unbindByTelegramId((int)($result['telegram_id'] ?? 0));
    }

    public function initiateReset(int $telegramId): ?string {
        $stmt = $this->pdo->prepare("SELECT confirmed FROM `{$this->table}` WHERE telegram_id = :telegram_id");
        $stmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($result) || !($result['confirmed'] ?? false)) {
            return null; // Not bound, cannot initiate reset
        }

        $code = $this->codeGenerator->generate();
        $updateStmt = $this->pdo->prepare("UPDATE `{$this->table}` SET reset_code = :code, reset_timestamp = :time WHERE telegram_id = :telegram_id");
        $updateStmt->bindParam(":code", $code, PDO::PARAM_STR);
        $updateStmt->bindValue(":time", time(), PDO::PARAM_INT);
        $updateStmt->bindParam(":telegram_id", $telegramId, PDO::PARAM_INT);
        $updateStmt->execute();
        return $code;
    }
}

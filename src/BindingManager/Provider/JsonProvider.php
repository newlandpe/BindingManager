<?php

declare(strict_types=1);

namespace BindingManager\Provider;

use BindingManager\Main;
use BindingManager\Util\CodeGenerator;
use pocketmine\utils\Config;

class JsonProvider implements DataProviderInterface {

    private Config $dataFile;
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
        $filePath = $main->getDataFolder() . ($config['file'] ?? 'bindings.json');
        $this->dataFile = new Config($filePath, Config::JSON);
        $this->codeGenerator = $codeGenerator;
        $this->bindingCodeTimeoutSeconds = (int) ($config['binding_code_timeout_seconds'] ?? 300);
    }

    public function getBindingStatus(int $telegramId): int {
        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data)) return BindingStatus::NOT_BOUND; // Not bound or invalid data

        if ((bool)($data['confirmed'] ?? false)) {
            return BindingStatus::CONFIRMED; // Confirmed
        }

        // If pending, check if the code has expired
        if (isset($data['code']) && isset($data['timestamp'])) {
            if (time() - (int) $data['timestamp'] > 300) {
                // Code expired, remove the pending binding
                $this->dataFile->remove((string) $telegramId);
                $this->dataFile->save();
                return BindingStatus::NOT_BOUND; // Treat as not bound
            }
        }
        return BindingStatus::PENDING; // Pending
    }

    public function getBoundPlayerName(int $telegramId): ?string {
        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data)) return null;
        return (isset($data['player_name']) && is_string($data['player_name'])) ? $data['player_name'] : null;
    }

    public function initiateBinding(string $playerName, int $telegramId): ?string {
        if ($this->getBindingStatus($telegramId) !== BindingStatus::NOT_BOUND) {
            return null; // Already bound or pending
        }
        if ($this->isPlayerNameBound($playerName)) {
            return null; // Player name already taken
        }

        $code = $this->codeGenerator->generate();
        $this->dataFile->set((string) $telegramId, [
            'player_name' => strtolower($playerName),
            'confirmed' => false,
            'code' => $code,
            'timestamp' => time(),
            'notifications_enabled' => true
        ]);
        $this->dataFile->save();
        return $code;
    }

    public function confirmBinding(string $playerName, string $code): bool {
        $telegramId = $this->getTelegramIdByPlayerName($playerName);
        if ($telegramId === null) return false;

        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data)) return false;

        if (!($data['confirmed'] ?? false) && (($data['code'] ?? '') === $code)) {
            // Code expires after 5 minutes (300 seconds)
            if (time() - (int)($data['timestamp'] ?? 0) > $this->bindingCodeTimeoutSeconds) {
                return false; // Code expired
            }

            $data['confirmed'] = true;
            unset($data['code'], $data['timestamp']); // Clean up used code and timestamp
            $this->dataFile->set((string) $telegramId, $data);
            $this->dataFile->save();
            return true;
        }
        return false;
    }

    public function unbindByTelegramId(int $telegramId): bool {
        if ($this->dataFile->exists((string) $telegramId)) {
            $this->dataFile->remove((string) $telegramId);
            $this->dataFile->save();
            return true;
        }
        return false;
    }

    public function isPlayerNameBound(string $playerName): bool {
        foreach ($this->dataFile->getAll() as $data) {
            if (is_array($data) && isset($data['player_name']) && strtolower($data['player_name']) === strtolower($playerName) && ($data['confirmed'] ?? false)) {
                return true;
            }
        }
        return false;
    }

    public function toggleNotifications(int $telegramId): bool {
        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data)) {
            return false;
        }
        $data['notifications_enabled'] = !(($data['notifications_enabled'] ?? true));
        $this->dataFile->set((string) $telegramId, $data);
        $this->dataFile->save();
        return $data['notifications_enabled'];
    }

    public function areNotificationsEnabled(int $telegramId): bool {
        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data)) {
            return false;
        }
        return (bool) ($data['notifications_enabled'] ?? false);
    }

    public function getTelegramIdByPlayerName(string $playerName): ?int {
        foreach ($this->dataFile->getAll() as $telegramId => $data) {
            if (is_array($data) && isset($data['player_name']) && is_string($data['player_name']) && strtolower($data['player_name']) === strtolower($playerName)) {
                return (int) $telegramId;
            }
        }
        return null;
    }

    public function initiateUnbinding(int $telegramId): ?string {
        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data) || !($data['confirmed'] ?? false)) {
            return null; // Not bound, cannot initiate unbinding
        }

        $code = $this->codeGenerator->generate();
        $data['unbind_code'] = $code;
        $data['unbind_timestamp'] = time();
        $this->dataFile->set((string) $telegramId, $data);
        $this->dataFile->save();
        return $code;
    }

    public function confirmUnbinding(string $playerName, string $code): bool {
        $telegramId = $this->getTelegramIdByPlayerName($playerName);
        if ($telegramId === null) return false;

        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data)) return false;

        if (isset($data['unbind_code']) && $data['unbind_code'] === $code) {
            // Code expires after 5 minutes (300 seconds)
            if (time() - ($data['unbind_timestamp'] ?? 0) > $this->bindingCodeTimeoutSeconds) {
                // Code expired, clear unbind request
                unset($data['unbind_code'], $data['unbind_timestamp']);
                $this->dataFile->set((string) $telegramId, $data);
                $this->dataFile->save();
                return false;
            }

            // Code is valid, perform unbinding
            return $this->unbindByTelegramId($telegramId);
        }
        return false;
    }

    public function initiateReset(int $telegramId): ?string {
        $data = $this->dataFile->get((string) $telegramId);
        if (!is_array($data) || !($data['confirmed'] ?? false)) {
            return null; // Not bound, cannot initiate reset
        }

        $code = $this->codeGenerator->generate();
        $data['reset_code'] = $code;
        $data['reset_timestamp'] = time();
        $this->dataFile->set((string) $telegramId, $data);
        $this->dataFile->save();
        return $code;
    }
}

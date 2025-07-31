<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Service;

use newlandpe\BindingManager\Event\AccountBoundEvent;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use pocketmine\Server;

class BindingService {

    private DataProviderInterface $dataProvider;

    public function __construct(DataProviderInterface $dataProvider) {
        $this->dataProvider = $dataProvider;
    }

    public function confirmBinding(string $playerName, string $code): bool {
        $telegramId = $this->dataProvider->getTelegramIdByPlayerName($playerName);
        if ($telegramId === null) return false;

        if ($this->dataProvider->confirmBinding($playerName, $code)) {
            $player = Server::getInstance()->getPlayerExact($playerName);
            if ($player !== null) {
                $event = new AccountBoundEvent($player, $telegramId);
                $event->call();
                if ($event->isCancelled()) {
                    // Rollback the confirmation if the event is cancelled
                    $this->dataProvider->unbindByTelegramId($telegramId);
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    public function initiateBinding(string $playerName, int $telegramId): ?string {
        return $this->dataProvider->initiateBinding($playerName, $telegramId);
    }

    public function unbindByTelegramId(int $telegramId): bool {
        $playerName = $this->dataProvider->getBoundPlayerName($telegramId);
        if ($this->dataProvider->unbindByTelegramId($telegramId)) {
            if ($playerName !== null) {
                $player = Server::getInstance()->getOfflinePlayer($playerName);
                if ($player !== null) {
                    $event = new AccountUnboundEvent($player, $telegramId);
                    $event->call();
                }
            }
            return true;
        }
        return false;
    }

    public function confirmUnbinding(string $playerName, string $code): bool {
        return $this->dataProvider->confirmUnbinding($playerName, $code);
    }

    public function initiateUnbinding(int $telegramId): ?string {
        return $this->dataProvider->initiateUnbinding($telegramId);
    }
}

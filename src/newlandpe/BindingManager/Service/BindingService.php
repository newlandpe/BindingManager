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

namespace newlandpe\BindingManager\Service;

use newlandpe\BindingManager\Event\AccountBoundEvent;
use newlandpe\BindingManager\Event\AccountUnboundEvent;
use newlandpe\BindingManager\Provider\BindingStatus;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Util\CodeGenerator;
use newlandpe\BindingManager\Main;
use pocketmine\utils\Config;

class BindingService {

    private DataProviderInterface $dataProvider;
    private CodeGenerator $codeGenerator;
    private int $bindingCodeTimeoutSeconds;
    private Main $plugin;

    public function __construct(DataProviderInterface $dataProvider, CodeGenerator $codeGenerator, Config $config, Main $plugin) {
        $this->dataProvider = $dataProvider;
        $this->codeGenerator = $codeGenerator;
        $this->bindingCodeTimeoutSeconds = (int)$config->get('binding-code-timeout-seconds', 300);
        $this->plugin = $plugin;
    }

    public function getPlayerBindingStatus(string $playerName): int {
        if ($this->isPlayerNameBound($playerName)) {
            return BindingStatus::CONFIRMED;
        }
        if ($this->dataProvider->findTemporaryBindingByPlayerName($playerName) !== null) {
            return BindingStatus::PENDING;
        }
        return BindingStatus::NOT_BOUND;
    }

    public function initiatePlayerBinding(string $playerName, int $telegramId): ?string {
        if ($this->isPlayerNameBound($playerName)) {
            return null; // Player name already bound to another Telegram account
        }

        $boundAccounts = $this->getBoundPlayerNames($telegramId);
        if (in_array(strtolower($playerName), array_map('strtolower', $boundAccounts), true)) {
            return null; // This player is already bound to this Telegram account
        }

        if ($this->dataProvider->findTemporaryBindingByPlayerName($playerName) !== null) {
            return null; // There is already a pending binding for this player
        }

        $code = $this->codeGenerator->generate();
        $expiresAt = time() + $this->bindingCodeTimeoutSeconds;

        if ($this->dataProvider->createTemporaryBinding($playerName, $telegramId, $code, $expiresAt)) {
            return $code;
        }

        return null;
    }

    public function confirmPlayerBinding(string $playerName, string $code): bool {
        $bindingData = $this->dataProvider->findTemporaryBindingByCode($code);

        if ($bindingData === null || strtolower($bindingData['player_name']) !== strtolower($playerName) || time() > $bindingData['expires_at']) {
            if ($bindingData !== null && time() > $bindingData['expires_at']) {
                $this->dataProvider->deleteTemporaryBinding($code);
            }
            return false;
        }

        $telegramId = (int)$bindingData['telegram_id'];
        $this->dataProvider->addPermanentBinding($telegramId, $playerName);
        $this->dataProvider->deleteTemporaryBinding($code);

        $player = Server::getInstance()->getPlayerExact($playerName);
        if ($player !== null) {
            $event = new AccountBoundEvent($player, $telegramId);
            $event->call();
            if ($event->isCancelled()) {
                $this->dataProvider->removePermanentBinding($telegramId, $playerName);
                return false;
            }
        }
        return true;
    }

    public function initiateUnbinding(int $telegramId, string $playerName): ?string {
        $boundNames = $this->getBoundPlayerNames($telegramId);
        if (!in_array(strtolower($playerName), array_map('strtolower', $boundNames), true)) {
            return null; // This player is not bound to this telegram account
        }

        $code = $this->codeGenerator->generate();
        $expiresAt = time() + $this->bindingCodeTimeoutSeconds;
        $this->dataProvider->createTemporaryUnbindCode($telegramId, $playerName, $code, $expiresAt);
        return $code;
    }

    public function confirmUnbinding(string $playerName, string $code): bool {
        $unbindData = $this->dataProvider->findTemporaryUnbindCode($code);

        if ($unbindData === null || strtolower($unbindData['player_name']) !== strtolower($playerName) || time() > $unbindData['expires_at']) {
            if ($unbindData !== null && time() > $unbindData['expires_at']) {
                $this->dataProvider->deleteTemporaryUnbindCode($code);
            }
            return false;
        }

        $telegramId = (int)$unbindData['telegram_id'];

        // User-initiated unbind
        if ($this->removePermanentBinding($telegramId, $playerName, AccountUnboundEvent::CAUSE_USER_REQUEST)) {
            $this->dataProvider->deleteTemporaryUnbindCode($code);
            return true;
        }
        return false;
    }

    public function deleteTemporaryBinding(string $code): void {
        $this->dataProvider->deleteTemporaryBinding($code);
    }

    public function removePermanentBinding(int $telegramId, string $playerName, int $cause = AccountUnboundEvent::CAUSE_USER_REQUEST): bool {
        if ($this->dataProvider->removePermanentBinding($telegramId, $playerName)) {
            $player = Server::getInstance()->getOfflinePlayer($playerName);
            $event = new AccountUnboundEvent($this->plugin, $player, $telegramId, $cause);
            $event->call();
            return true;
        }
        return false;
    }

    public function getBoundPlayerNames(int $telegramId): array {
        return $this->dataProvider->getBoundPlayerNames($telegramId);
    }

    public function isPlayerNameBound(string $playerName): bool {
        return $this->dataProvider->isPlayerNameBound($playerName);
    }

    public function getTelegramIdByPlayerName(string $playerName): ?int {
        return $this->dataProvider->getTelegramIdByPlayerName($playerName);
    }

    public function toggleNotifications(string $playerName): bool {
        return $this->dataProvider->toggleNotifications($playerName);
    }

    public function areNotificationsEnabled(string $playerName): bool {
        return $this->dataProvider->areNotificationsEnabled($playerName);
    }

    public function isTwoFactorEnabled(string $playerName): bool {
        return $this->dataProvider->isTwoFactorEnabled($playerName);
    }

    public function setTwoFactor(string $playerName, bool $enabled): void {
        $this->dataProvider->setTwoFactor($playerName, $enabled);
    }

    public function cleanupExpiredBindings(): void {
        $this->dataProvider->deleteExpiredTemporaryBindings();
    }
}

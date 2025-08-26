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

use Closure;
use newlandpe\BindingManager\Event\AccountBoundEvent;
use newlandpe\BindingManager\Event\AccountUnboundEvent;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Util\CodeGenerator;
use pocketmine\Server;
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

    public function getPlayerBindingStatus(string $playerName, Closure $callback): void {
        $this->isPlayerNameBound($playerName, function (bool $isBound) use ($playerName, $callback): void {
            if ($isBound) {
                $callback(BindingStatus::CONFIRMED);
                return;
            }
            $this->dataProvider->findTemporaryBindingByPlayerName($playerName, function (?array $binding) use ($callback): void {
                if ($binding !== null) {
                    $callback(BindingStatus::PENDING);
                } else {
                    $callback(BindingStatus::NOT_BOUND);
                }
            });
        });
    }

    public function initiatePlayerBinding(string $playerName, int $telegramId, Closure $callback): void {
        $this->isPlayerNameBound($playerName, function (bool $isBound) use ($playerName, $telegramId, $callback): void {
            if ($isBound) {
                $callback(null); // Player name already bound to another Telegram account
                return;
            }

            $this->getBoundPlayerNames($telegramId, function (array $boundAccounts) use ($playerName, $telegramId, $callback): void {
                if (in_array(strtolower($playerName), array_map('strtolower', $boundAccounts), true)) {
                    $callback(null); // This player is already bound to this Telegram account
                    return;
                }

                $this->dataProvider->findTemporaryBindingByPlayerName($playerName, function (?array $binding) use ($playerName, $telegramId, $callback): void {
                    if ($binding !== null) {
                        $callback(null); // There is already a pending binding for this player
                        return;
                    }

                    $code = $this->codeGenerator->generate();
                    $expiresAt = time() + $this->bindingCodeTimeoutSeconds;

                    $this->dataProvider->createTemporaryBinding($playerName, $telegramId, $code, $expiresAt, function (bool $success) use ($code, $callback): void {
                        if ($success) {
                            $callback($code);
                        } else {
                            $callback(null);
                        }
                    });
                });
            });
        });
    }

    public function confirmPlayerBinding(string $playerName, string $code, Closure $callback): void {
        $this->dataProvider->findTemporaryBindingByCode($code, function (?array $bindingData) use ($playerName, $code, $callback): void {
            if ($bindingData === null || strtolower($bindingData['player_name']) !== strtolower($playerName) || time() > $bindingData['expires_at']) {
                if ($bindingData !== null && time() > $bindingData['expires_at']) {
                    $this->dataProvider->deleteTemporaryBinding($code);
                }
                $callback(false);
                return;
            }

            $telegramId = (int)$bindingData['telegram_id'];
            $this->dataProvider->addPermanentBinding($telegramId, $playerName, function (bool $success) use ($telegramId, $playerName, $code, $callback): void {
                if (!$success) {
                    $callback(false);
                    return;
                }

                $this->dataProvider->deleteTemporaryBinding($code);

                $player = Server::getInstance()->getPlayerExact($playerName);
                if ($player !== null) {
                    $event = new AccountBoundEvent($this->plugin, $player, $telegramId);
                    $event->call();
                    if ($event->isCancelled()) {
                        $this->dataProvider->removePermanentBinding($telegramId, $playerName, function (bool $removed) use ($callback): void {
                            $callback(false);
                        });
                        return;
                    }
                }
                $callback(true);
            });
        });
    }

    public function initiateUnbinding(int $telegramId, string $playerName, Closure $callback): void {
        $this->getBoundPlayerNames($telegramId, function (array $boundNames) use ($telegramId, $playerName, $callback): void {
            if (!in_array(strtolower($playerName), array_map('strtolower', $boundNames), true)) {
                $callback(null); // This player is not bound to this telegram account
                return;
            }

            $code = $this->codeGenerator->generate();
            $expiresAt = time() + $this->bindingCodeTimeoutSeconds;
            $this->dataProvider->createTemporaryUnbindCode($telegramId, $playerName, $code, $expiresAt, function (bool $success) use ($code, $callback): void {
                if ($success) {
                    $callback($code);
                } else {
                    $callback(null);
                }
            });
        });
    }

    public function confirmUnbinding(string $playerName, string $code, Closure $callback): void {
        $this->dataProvider->findTemporaryUnbindCode($code, function (?array $unbindData) use ($playerName, $code, $callback): void {
            if ($unbindData === null || strtolower($unbindData['player_name']) !== strtolower($playerName) || time() > $unbindData['expires_at']) {
                if ($unbindData !== null && time() > $unbindData['expires_at']) {
                    $this->dataProvider->deleteTemporaryUnbindCode($code);
                }
                $callback(false);
                return;
            }

            $telegramId = (int)$unbindData['telegram_id'];

            $this->removePermanentBinding($telegramId, $playerName, AccountUnboundEvent::CAUSE_USER_REQUEST, function (bool $success) use ($code, $callback): void {
                if ($success) {
                    $this->dataProvider->deleteTemporaryUnbindCode($code);
                    $callback(true);
                } else {
                    $callback(false);
                }
            });
        });
    }

    public function deleteTemporaryBinding(string $code): void {
        $this->dataProvider->deleteTemporaryBinding($code);
    }

    public function removePermanentBinding(int $telegramId, string $playerName, int $cause = AccountUnboundEvent::CAUSE_USER_REQUEST, ?Closure $callback = null): void {
        $this->dataProvider->removePermanentBinding($telegramId, $playerName, function (bool $success) use ($telegramId, $playerName, $cause, $callback): void {
            if ($success) {
                $player = Server::getInstance()->getOfflinePlayer($playerName);
                $event = new AccountUnboundEvent($this->plugin, $player, $telegramId, $cause);
                $event->call();
            }
            $callback($success);
        });
    }

    public function getBoundPlayerNames(int $telegramId, Closure $callback): void {
        $this->dataProvider->getBoundPlayerNames($telegramId, $callback);
    }

    public function isPlayerNameBound(string $playerName, Closure $callback): void {
        $this->dataProvider->isPlayerNameBound($playerName, $callback);
    }

    public function getTelegramIdByPlayerName(string $playerName, Closure $callback): void {
        $this->dataProvider->getTelegramIdByPlayerName($playerName, $callback);
    }

    public function toggleNotifications(string $playerName, Closure $callback): void {
        $this->dataProvider->toggleNotifications($playerName, $callback);
    }

    public function areNotificationsEnabled(string $playerName, Closure $callback): void {
        $this->dataProvider->areNotificationsEnabled($playerName, $callback);
    }

    public function isTwoFactorEnabled(string $playerName, Closure $callback): void {
        $this->dataProvider->isTwoFactorEnabled($playerName, $callback);
    }

    public function setTwoFactor(string $playerName, bool $enabled): void {
        $this->dataProvider->setTwoFactor($playerName, $enabled);
    }

    public function cleanupExpiredBindings(): void {
        $this->dataProvider->deleteExpiredTemporaryBindings();
    }
}

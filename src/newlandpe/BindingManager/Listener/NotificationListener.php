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

namespace newlandpe\BindingManager\Listener;

use newlandpe\BindingManager\Event\AccountUnboundEvent;
use newlandpe\BindingManager\Event\SendNotificationEvent;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\event\Listener;

class NotificationListener implements Listener {

    private BindingService $bindingService;
    private TelegramBot $bot;
    private LanguageManager $lang;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
        $this->bot = $container->get(TelegramBot::class);
        $this->lang = $container->get(LanguageManager::class);
    }

    /**
     * @param SendNotificationEvent $event
     * @priority NORMAL
     * @ignoreCancelled false
     */
    public function onSendNotification(SendNotificationEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if ($this->bindingService->areNotificationsEnabled($playerName)) {
            $telegramId = $this->bindingService->getTelegramIdByPlayerName($playerName);
            if ($telegramId !== null) {
                $this->bot->sendMessage($telegramId, $event->getMessage());
            }
        }
    }

    /**
     * @param AccountUnboundEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onAccountUnbound(AccountUnboundEvent $event): void {
        $playerName = $event->getPlayer()->getName();
        $telegramId = $event->getTelegramId();
        $cause = $event->getCause();

        $messageKey = null;
        if ($cause === AccountUnboundEvent::CAUSE_XAUTH_UNREGISTER) {
            $messageKey = "telegram-autounbind-notification";
        } elseif ($cause === AccountUnboundEvent::CAUSE_ADMIN_FORCE) {
            $messageKey = "telegram-admin-unbind-notification";
        }

        if ($messageKey !== null) {
            // We don't check areNotificationsEnabled here, as this is a critical security-related notification.
            $message = $this->lang->get($messageKey, ["player_name" => $playerName]);
            $this->bot->sendMessage($telegramId, $message);
        }
    }
}

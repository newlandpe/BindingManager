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

use Luthfi\XAuth\event\PlayerUnregisterEvent;
use newlandpe\BindingManager\Event\AccountUnboundEvent;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\event\Listener;

class XAuthListener implements Listener {

    private BindingService $bindingService;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
    }

    /**
     * Called when a player unregisters their XAuth account.
     * We will unbind their account from BindingManager as well.
     *
     * @param PlayerUnregisterEvent $event
     * @priority NORMAL
     * @ignoreCancelled true
     */
    public function onPlayerUnregister(PlayerUnregisterEvent $event): void {
        $playerName = $event->getPlayer()->getName();
        $telegramId = $this->bindingService->getTelegramIdByPlayerName($playerName);

        if ($telegramId !== null) {
            $this->bindingService->removePermanentBinding($telegramId, $playerName, AccountUnboundEvent::CAUSE_XAUTH_UNREGISTER);
        }
    }
}

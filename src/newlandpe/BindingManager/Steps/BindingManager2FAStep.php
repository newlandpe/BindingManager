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

namespace newlandpe\BindingManager\Steps;

use Luthfi\XAuth\steps\AuthenticationStep;
use newlandpe\BindingManager\Main;
use pocketmine\player\Player;

class BindingManager2FAStep implements AuthenticationStep {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function getId(): string {
        return 'binding_manager_2fa';
    }

    public function start(Player $player): void {
        // Logic to start 2FA process for the player
        $twoFactorAuthService = $this->plugin->getContainer()->get(\newlandpe\BindingManager\Service\TwoFactorAuthService::class);
        $twoFactorAuthService->start2FAProcess($player);
        // Note: The completion of this step is expected to be handled by BindingManager's
        // internal logic (e.g., after successful 2FA verification).
        // It should eventually call $this->plugin->getXAuth()->getAuthenticationFlowManager()->completeStep($player, $this->getId());
        // or skipStep($player, $this->getId());
    }

    public function complete(Player $player): void {
        // This method should be called by BindingManager's internal 2FA verification logic
        // when 2FA is successfully completed for the player.
        $this->plugin->getLogger()->debug("BindingManager 2FA step completed for " . $player->getName());
        $xAuthPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("XAuth");
        if ($xAuthPlugin instanceof \Luthfi\XAuth\Main) {
            $xAuthPlugin->getAuthenticationFlowManager()->completeStep($player, $this->getId());
        }
    }

    public function skip(Player $player): void {
        // This method should be called if 2FA is not required for the player,
        // or if it's bypassed by configuration.
        $this->plugin->getLogger()->debug("BindingManager 2FA step skipped for " . $player->getName());
        $xAuthPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin("XAuth");
        if ($xAuthPlugin instanceof \Luthfi\XAuth\Main) {
            $xAuthPlugin->getAuthenticationFlowManager()->skipStep($player, $this->getId());
        }
    }
}

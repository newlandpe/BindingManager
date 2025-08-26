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

namespace newlandpe\BindingManager\Command\Game;

use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Service\BindingService;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use newlandpe\BindingManager\Main;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;
use newlandpe\BindingManager\Util\ServiceContainer;

class ConfirmCommand extends Command implements PluginOwned {

    private BindingService $bindingService;
    private LanguageManager $lang;
    private Main $plugin;

    public function __construct(ServiceContainer $container) {
        parent::__construct("confirm", "Confirms the binding or unbinding of your account.", "/confirm <code>");
        $this->setPermission("bindingmanager.command.confirm");
        $this->plugin = $container->get(Main::class);
        $this->bindingService = $container->get(BindingService::class);
        $this->lang = $container->get(LanguageManager::class);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return true;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage($this->lang->get("command-only-in-game"));
            return true;
        }

        if (!isset($args[0]) || $args[0] === '') {
            $sender->sendMessage($this->getUsage());
            return true;
        }

        $code = $args[0];
        $playerName = $sender->getName();

        // First, try to confirm a new account binding
        $this->bindingService->confirmPlayerBinding($playerName, $code, function (bool $bindSuccess) use ($sender, $playerName, $code): void {
            if ($bindSuccess) {
                $sender->sendMessage($this->lang->get("command-confirm-bind-success"));
                return;
            }

            // If that fails, try to confirm an account unbinding
            $this->bindingService->confirmUnbinding($playerName, $code, function (bool $unbindSuccess) use ($sender, $playerName): void {
                if ($unbindSuccess) {
                    $sender->sendMessage($this->lang->get("command-confirm-unbind-success", ['player' => $playerName]));
                } else {
                    // If both fail, send a generic failure message. We can check which type of code it was to give a better message.
                    // For now, we will assume the most common case is binding failure.
                    $sender->sendMessage($this->lang->get("command-confirm-bind-fail"));
                }
            });
        });

        return true;
    }
    
    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}

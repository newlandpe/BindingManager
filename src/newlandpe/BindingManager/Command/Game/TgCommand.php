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
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use newlandpe\BindingManager\Main;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginOwned;

class TgCommand extends Command implements PluginOwned {

    private BindingService $bindingService;
    private LanguageManager $lang;
    private TelegramBot $bot;
    private Main $plugin;

    public function __construct(ServiceContainer $container) {
        parent::__construct("tg", "Manage your Telegram bindings.", "/tg <subcommand>");
        $this->setPermission("bindingmanager.command.tg");
        $this->plugin = $container->get(Main::class);
        $this->bindingService = $container->get(BindingService::class);
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) {
            return false;
        }

        if (!isset($args[0])) {
            $sender->sendMessage($this->lang->get("command-usage-tg"));
            return false;
        }

        switch (strtolower($args[0])) {
            case "help":
                if ($sender->hasPermission("bindingmanager.command.forceunbind")) {
                    $sender->sendMessage($this->lang->get("command-tg-help-admin"));
                } else {
                    $sender->sendMessage($this->lang->get("command-tg-help-player"));
                }
                break;
            case "unbind":
                if (!$sender instanceof Player) {
                    $sender->sendMessage($this->lang->get("command-only-in-game"));
                    return false;
                }
                if (!isset($args[1]) || strtolower($args[1]) !== "confirm" || !isset($args[2]) || $args[2] === '') {
                    $sender->sendMessage($this->lang->get("command-usage-tg-unbind"));
                    return false;
                }
                $code = $args[2];
                $this->bindingService->confirmUnbinding($sender->getName(), $code, function (bool $success) use ($sender): void {
                    if ($success) {
                        $sender->sendMessage($this->lang->get("command-unbind-confirm-success", ['player' => $sender->getName()]));
                    } else {
                        $sender->sendMessage($this->lang->get("command-unbind-confirm-fail", ['player' => $sender->getName()]));
                    }
                });
                break;
            //TODO: The in-game reset feature is not fully implemented in the service layer.
            // case "reset":
            //     if (!$sender instanceof Player) {
            //         $sender->sendMessage($this->lang->get("command-only-in-game"));
            //         return false;
            //     }
            //     // This requires a new method in BindingService and DataProvider to handle in-game reset codes.
            //     $code = $this->bindingService->initiateInGameReset($sender->getName()); 
            //     if ($code === null) {
            //         $sender->sendMessage($this->lang->get("command-ingame-reset-fail"));
            //         return false;
            //     }
            //     $telegramId = $this->bindingService->getTelegramIdByPlayerName($sender->getName());
            //     if ($telegramId !== null) {
            //         $this->bot->sendMessage($telegramId, $this->lang->get("telegram-ingame-reset-alert"));
            //     }
            //     $sender->sendMessage($this->lang->get("command-ingame-reset-initiated", ["code" => $code]));
            //     break;
            // case "confirmreset":
            //     if (!$sender->hasPermission("bindingmanager.command.forceunbind")) {
            //         $sender->sendMessage($this->lang->get("command-no-permission"));
            //         return false;
            //     }
            //     if (!isset($args[1]) || !isset($args[2])) {
            //         $sender->sendMessage($this->lang->get("command-usage-tg-confirmreset"));
            //         return false;
            //     }
            //     $playerName = $args[1];
            //     $code = $args[2];
            //     // This requires a new method in BindingService and DataProvider.
            //     if ($this->bindingService->confirmInGameReset($playerName, $code)) {
            //         $sender->sendMessage($this->lang->get("command-confirmreset-success", ["player_name" => $playerName]));
            //     } else {
            //         $sender->sendMessage($this->lang->get("command-confirmreset-fail", ["player_name" => $playerName]));
            //     }
            //     break;
            case "forceunbind":
                if (!$sender->hasPermission("bindingmanager.command.forceunbind")) {
                    $sender->sendMessage($this->lang->get("command-no-permission"));
                    return false;
                }
                if (!isset($args[1]) || $args[1] === '') {
                    $sender->sendMessage($this->lang->get("command-usage-tg-forceunbind"));
                    return false;
                }
                $playerName = $args[1];
                $this->bindingService->getTelegramIdByPlayerName($playerName, function (?int $telegramId) use ($sender, $playerName): void {
                    if ($telegramId !== null) {
                        $this->bindingService->removePermanentBinding($telegramId, $playerName, \newlandpe\BindingManager\Event\AccountUnboundEvent::CAUSE_ADMIN_FORCE, function (bool $success) use ($sender, $playerName): void {
                            if ($success) {
                                $sender->sendMessage($this->lang->get("command-forceunbind-success", ["player_name" => $playerName]));
                            } else {
                                $sender->sendMessage($this->lang->get("command-forceunbind-fail", ["player_name" => $playerName]));
                            }
                        });
                    } else {
                        $sender->sendMessage($this->lang->get("command-forceunbind-player-not-bound", ["player_name" => $playerName]));
                    }
                });
                break;
            default:
                $sender->sendMessage($this->lang->get("command-unknown-subcommand"));
                break;
        }
        return true;
    }

    public function getOwningPlugin(): Plugin {
        return $this->plugin;
    }
}

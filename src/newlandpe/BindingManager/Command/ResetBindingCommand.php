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

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\utils\Config;

class ResetBindingCommand implements CommandInterface {

    private BindingService $bindingService;
    private Config $config;
    private LanguageManager $lang;
    private TelegramBot $bot;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
        $this->config = $container->get(Config::class);
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
    }

    public function execute(CommandContext $context): bool {
        $chatId = (int)($context->message['chat']['id'] ?? 0);
        $fromId = (int)($context->message['from']['id'] ?? 0);
        $args = $context->args;

        $admins = $this->config->get('admins', []);
        if (!in_array($fromId, $admins, true)) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-admin-permission-denied"));
            return true;
        }

        if (!isset($args[0])) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-reset-usage"));
            return true;
        }

        $playerName = $args[0];
        $telegramId = $this->bindingService->getTelegramIdByPlayerName($playerName);

        if ($telegramId === null) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-reset-fail-not-bound", ["player_name" => $playerName]));
            return true;
        }

        if ($this->bindingService->removePermanentBinding($telegramId, $playerName)) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-reset-success", ["player_name" => $playerName]));
        } else {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-reset-fail", ["player_name" => $playerName]));
        }

        return true;
    }
}

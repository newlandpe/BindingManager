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
use pocketmine\Server;
use pocketmine\utils\Config;

class AdminPlayerInfoCommand implements CommandInterface {

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
        $chatId = 0;
        if (isset($context->message['chat'])) {
            $chatId = (int)($context->message['chat']['id'] ?? 0);
        }

        $fromId = 0;
        if (isset($context->message['from'])) {
            $fromId = (int)($context->message['from']['id'] ?? 0);
        }
        $args = $context->args;
        $lang = $this->lang;

        $admins = $this->config->get('admins', []);
        if (!in_array($fromId, $admins, true)) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-permission-denied"));
            return true;
        }

        $targetTelegramId = null;

        if (isset($context->message['forward_from']['id'])) {
            $targetTelegramId = (int)$context->message['forward_from']['id'];
        } elseif (isset($context->message['reply_to_message']['from']['id'])) {
            $targetTelegramId = (int)$context->message['reply_to_message']['from']['id'];
        } elseif (isset($args[0]) && is_numeric($args[0])) {
            $targetTelegramId = (int)$args[0];
        } else {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-playerinfo-usage"));
            return true;
        }

        $boundPlayerNames = $this->bindingService->getBoundPlayerNames($targetTelegramId);

        if (count($boundPlayerNames) > 0) {
            $playerList = implode(", ", $boundPlayerNames);
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-playerinfo-success", [
                "telegram_id" => $targetTelegramId,
                "player_list" => $playerList
            ]));
        } else {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-playerinfo-not-found", [
                "telegram_id" => $targetTelegramId
            ]));
        }
        return true;
    }
}

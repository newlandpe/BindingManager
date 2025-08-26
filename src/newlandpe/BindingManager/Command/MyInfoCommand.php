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

use newlandpe\BindingManager\Event\PlayerDataInfoEvent;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\player\Player;
use pocketmine\Server;

class MyInfoCommand implements CommandInterface {

    private BindingService $bindingService;
    private Main $plugin;
    private Server $server;
    private LanguageManager $lang;
    private TelegramBot $bot;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
        $this->plugin = $container->get(Main::class);
        $this->server = $container->get(Server::class);
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
    }

    public function execute(CommandContext $context): bool {
        $chatId = 0;
        $fromId = 0;
        $isTelegramContext = false;

        if ($context->callbackQuery !== null) {
            $isTelegramContext = true;
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
            $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
        } elseif (isset($context->message)) {
            $isTelegramContext = true;
            $chatId = (int)($context->message['chat']['id'] ?? 0);
            $fromId = (int)($context->message['from']['id'] ?? 0);
        }

        $targetPlayerName = null;

        if (!empty($context->args[0])) {
            $targetPlayerName = $context->args[0];
        } else {
            // This case should ideally not be reached in Telegram if UI is correctly implemented
            // but as a safeguard, we check.
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-myinfo-player-required"));
            return true;
        }

        if ($isTelegramContext && $fromId !== 0) {
            $this->bindingService->getBoundPlayerNames($fromId, function (array $boundPlayers) use ($chatId, $targetPlayerName): void {
                if (!in_array(strtolower($targetPlayerName), array_map('strtolower', $boundPlayers), true)) {
                    $this->bot->sendMessage($chatId, $this->lang->get("telegram-myinfo-not-bound-to-you", ["player" => $targetPlayerName]));
                    return;
                }
                $this->displayPlayerInfo($chatId, $targetPlayerName);
            });
        } else {
            $this->displayPlayerInfo($chatId, $targetPlayerName);
        }

        return true;
    }

    private function displayPlayerInfo(int $chatId, string $playerName): void {
        $player = $this->server->getPlayerExact($playerName) ?? $this->server->getOfflinePlayer($playerName);

        if ($player === null) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-myinfo-player-not-found"));
            return;
        }

        $placeholders = [
            'nickname' => $player->getName(),
        ];

        if ($player instanceof Player && $player->isOnline()) {
            $template = $this->lang->get("telegram-myinfo-online");
            $placeholders['status'] = $this->lang->get("player-info-status-online");
            $placeholders['health'] = $player->getHealth();
            $pos = $player->getPosition();
            $placeholders['position'] = round($pos->getX()) . ", " . round($pos->getY()) . ", " . round($pos->getZ());
        } else {
            $template = $this->lang->get("telegram-myinfo-offline");
            $placeholders['status'] = $this->lang->get("player-info-status-offline");
        }

        $event = new PlayerDataInfoEvent($player, $this->plugin);
        $event->call();

        $allPlaceholders = array_merge($placeholders, $event->getPlaceholders());

        $infoText = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', fn(array $matches) => (string)($allPlaceholders[$matches[1]] ?? ''), $template);
        $infoText = preg_replace('/^\s*\n/m', '', $infoText ?? '');

        $this->bot->sendMessage($chatId, $infoText ?? '');
    }
}

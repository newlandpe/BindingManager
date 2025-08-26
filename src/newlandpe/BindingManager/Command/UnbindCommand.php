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

use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\player\Player;

class UnbindCommand implements CommandInterface {

    private BindingService $bindingService;
    private Main $plugin;
    private LanguageManager $lang;
    private TelegramBot $bot;
    private KeyboardFactory $keyboardFactory;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
        $this->plugin = $container->get(Main::class);
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
        $this->keyboardFactory = $container->get(KeyboardFactory::class);
    }

    public function execute(CommandContext $context): bool {
        $chatId = 0;
        $fromId = 0;

        if ($context->callbackQuery !== null) {
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
            $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
        } elseif (isset($context->message)) {
            $chatId = (int)($context->message['chat']['id'] ?? 0);
            $fromId = (int)($context->message['from']['id'] ?? 0);
        }

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        $targetPlayerName = $context->args[0] ?? null;

        if ($targetPlayerName === null) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-unbind-player-required"));
            return true;
        }

        $boundPlayers = $this->bindingService->getBoundPlayerNames($fromId);
        if (!in_array(strtolower($targetPlayerName), array_map('strtolower', $boundPlayers), true)) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-unbind-not-bound-to-you", ["player" => $targetPlayerName]));
            return true;
        }

        $code = $this->bindingService->initiateUnbinding($fromId, $targetPlayerName);
        if ($code === null) {
            $this->bot->sendMessage($chatId, $this->lang->get("telegram-unbind-fail", ["player" => $targetPlayerName]));
            return true;
        }

        // The Main class instance is already available via DI as $this->plugin
        // $this->plugin->setUserState($fromId, 'awaiting_unbind_confirm:' . $targetPlayerName); // This state seems to be handled elsewhere or is part of a deprecated flow.

        $this->bot->sendMessage($chatId, $this->lang->get("telegram-unbind-code", ['code' => $code, 'player' => $targetPlayerName]), $this->keyboardFactory->createUnbindCancelKeyboard());
        return true;
    }
}

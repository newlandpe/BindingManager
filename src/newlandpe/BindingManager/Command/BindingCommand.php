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
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;

class BindingCommand implements CommandInterface {

    private BindingService $bindingService;
    private LanguageManager $lang;
    private TelegramBot $bot;
    private KeyboardFactory $keyboardFactory;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
        $this->keyboardFactory = $container->get(KeyboardFactory::class);
    }

    public function execute(CommandContext $context): bool {
        $chatId = (int)($context->message['chat']['id'] ?? 0);
        $fromId = (int)($context->message['from']['id'] ?? 0);
        $args = $context->args;

        if ($chatId === 0 || $fromId === 0) {
            return true; // Cannot proceed without chat/sender ID
        }

        // This command should now only be executed with a player name argument
        // (typically from the user responding after being prompted for their nickname).
        if (!isset($args[0]) || $args[0] === '') {
            // The old logic of showing a menu here is deprecated.
            // Menu navigation is handled by menu:* and binding:* callbacks.
            // If the command is called directly without args, show usage.
            $this->bot->sendMessage($chatId, $this->lang->get('telegram-binding-usage'));
            return true;
        }

        $playerName = $args[0];
        // The service method was renamed for clarity, let's use the correct one.
        $code = $this->bindingService->initiatePlayerBinding($playerName, $fromId);

        if ($code === null) {
            // The service now handles the logic of checking if the player or telegram account is already bound.
            // We need a more specific message, but for now, let's use a generic failure message.
            // A better implementation would have the service return a reason for the failure.
            $this->bot->sendMessage($chatId, $this->lang->get('telegram-binding-player-already-bound'));
            return true;
        }

        $this->bot->sendMessage($chatId, $this->lang->get("chat-binding-code", ['code' => $code]), $this->keyboardFactory->createCancelKeyboard($code));
        return true;
    }
}

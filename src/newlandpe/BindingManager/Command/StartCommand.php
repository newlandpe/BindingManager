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
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;

class StartCommand implements CommandInterface {

    private LanguageManager $lang;
    private TelegramBot $bot;
    private KeyboardFactory $keyboardFactory;

    public function __construct(ServiceContainer $container) {
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
        $this->keyboardFactory = $container->get(KeyboardFactory::class);
    }

    public function execute(CommandContext $context): bool {
        $chatId = (int)($context->message['chat']['id'] ?? 0);

        if ($chatId === 0) {
            return true;
        }

        $keyboard = $this->keyboardFactory->createInitialMenu();
        $this->bot->sendMessage($chatId, $this->lang->get("telegram-start"), $keyboard);
        return true;
    }
}

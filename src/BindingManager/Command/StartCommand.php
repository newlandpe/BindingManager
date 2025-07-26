<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class StartCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        $chatId = $context->message['chat']['id'] ?? 0;
        $lang = $context->lang;
        $keyboardFactory = $context->keyboardFactory;

        if ($chatId === 0) {
            return true;
        }
        $keyboard = $keyboardFactory->createMainMenu();
        $this->bot->sendMessage($chatId, $lang->get("telegram-start"), $keyboard);
        return true;
    }
}

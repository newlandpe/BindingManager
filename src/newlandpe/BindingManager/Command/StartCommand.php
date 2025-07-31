<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Telegram\TelegramBot;

class StartCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        $chatId = 0;
        if (isset($context->message['chat']) && is_array($context->message['chat'])) {
            $chatId = (int)($context->message['chat']['id'] ?? 0);
        }
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

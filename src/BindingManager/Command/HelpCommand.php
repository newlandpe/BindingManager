<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class HelpCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        $chatId = (int) ($context->message['chat']['id'] ?? 0);
        $lang = $context->lang;

        if ($chatId === 0) {
            return true;
        }
        $this->bot->sendMessage($chatId, $lang->get("telegram-help"));
        return true;
    }
}

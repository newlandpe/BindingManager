<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class UnbindCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        $chatId = 0;
        $fromId = 0;

        if ($context->callbackQuery !== null && is_array($context->callbackQuery)) {
            $chatId = (int) ($context->callbackQuery['message']['chat']['id'] ?? 0);
            $fromId = (int) ($context->callbackQuery['from']['id'] ?? 0);
        } elseif (is_array($context->message)) {
            $chatId = (int) ($context->message['chat']['id'] ?? 0);
            $fromId = (int) ($context->message['from']['id'] ?? 0);
        }
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        $code = $dataProvider->initiateUnbinding((int) $fromId);
        if ($code === null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-unbind-fail"));
            return true;
        }

        $this->bot->sendMessage($chatId, $lang->get("telegram-unbind-code", ['code' => $code]));
        return true;
    }
}

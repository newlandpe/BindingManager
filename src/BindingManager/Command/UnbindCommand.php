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
            if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) {
                if (isset($context->callbackQuery['message']['chat']) && is_array($context->callbackQuery['message']['chat'])) {
                    $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
                }
            }
            if (isset($context->callbackQuery['from']) && is_array($context->callbackQuery['from'])) {
                $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
            }
        } elseif (is_array($context->message)) {
            if (isset($context->message['chat']) && is_array($context->message['chat'])) {
                $chatId = (int)($context->message['chat']['id'] ?? 0);
            }
            if (isset($context->message['from']) && is_array($context->message['from'])) {
                $fromId = (int)($context->message['from']['id'] ?? 0);
            }
        }
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        $code = $dataProvider->initiateUnbinding($fromId);
        if ($code === null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-unbind-fail"));
            return true;
        }

        $this->bot->sendMessage($chatId, $lang->get("telegram-unbind-code", ['code' => $code]));
        return true;
    }
}

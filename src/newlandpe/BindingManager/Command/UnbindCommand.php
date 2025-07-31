<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Telegram\TelegramBot;

class UnbindCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        $chatId = 0;
        $fromId = 0;

        if ($context->callbackQuery !== null) {
            if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) {
                if (isset($context->callbackQuery['message']['chat']) && is_array($context->callbackQuery['message']['chat'])) {
                    $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
                }
            }
            if (isset($context->callbackQuery['from']) && is_array($context->callbackQuery['from'])) {
                $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
            }
        } elseif (isset($context->message)) {
            if (isset($context->message['chat']) && is_array($context->message['chat'])) {
                $chatId = (int)($context->message['chat']['id'] ?? 0);
            }
            if (isset($context->message['from']) && is_array($context->message['from'])) {
                $fromId = (int)($context->message['from']['id'] ?? 0);
            }
        }
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;
        $keyboardFactory = $context->keyboardFactory;
        $main = Main::getInstance();

        if ($chatId === 0 || $fromId === 0 || $main === null) {
            return true;
        }

        $code = $this->bindingService->initiateUnbinding($fromId);
        if ($code === null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-unbind-fail"));
            return true;
        }

        $main->setUserState($fromId, 'awaiting_unbind_confirm');
        $this->bot->sendMessage($chatId, $lang->get("telegram-unbind-code", ['code' => $code]), $keyboardFactory->createUnbindCancelKeyboard());
        return true;
    }
}

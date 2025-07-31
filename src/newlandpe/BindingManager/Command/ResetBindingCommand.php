<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Telegram\TelegramBot;

class ResetBindingCommand implements CommandInterface {

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
        $dataProvider = $context->dataProvider;

        $main = Main::getInstance();
        if ($main === null) {
            $this->bot->sendMessage($chatId, "An internal error occurred.");
            return true;
        }

        if (!isset($context->message['chat']) || !is_array($context->message['chat']) || ($context->message['chat']['type'] ?? null) !== 'private') {
            $this->bot->sendMessage($chatId, $lang->get("telegram-command-private-only"));
            return true;
        }

        $code = $dataProvider->initiateReset($chatId);

        if ($code !== null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-reset-code", ["code" => $code]));
        } else {
            $this->bot->sendMessage($chatId, $lang->get("telegram-reset-fail"));
        }
        return true;
    }
}

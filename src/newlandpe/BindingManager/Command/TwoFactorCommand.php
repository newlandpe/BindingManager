<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\TelegramBot;

class TwoFactorCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        $chatId = 0;
        if (isset($context->message['chat']) && is_array($context->message['chat'])) {
            $chatId = (int)($context->message['chat']['id'] ?? 0);
        }

        $fromId = 0;
        if (isset($context->message['from']) && is_array($context->message['from'])) {
            $fromId = (int)($context->message['from']['id'] ?? 0);
        }
        $args = $context->args;
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        if ($dataProvider->getBindingStatus($fromId) !== 2) {
            $this->bot->sendMessage($chatId, $lang->get("2fa-not-bound"));
            return true;
        }

        $subCommand = strtolower($args[0] ?? 'status');

        switch ($subCommand) {
            case 'enable':
                $dataProvider->setTwoFactor($fromId, true);
                $this->bot->sendMessage($chatId, $lang->get("2fa-enabled"));
                break;
            case 'disable':
                $dataProvider->setTwoFactor($fromId, false);
                $this->bot->sendMessage($chatId, $lang->get("2fa-disabled"));
                break;
            case 'status':
                $status = $dataProvider->isTwoFactorEnabled($fromId) ? "enabled" : "disabled";
                $this->bot->sendMessage($chatId, $lang->get("2fa-status-" . $status));
                break;
            default:
                $this->bot->sendMessage($chatId, $lang->get("2fa-usage"));
                break;
        }

        return true;
    }
}

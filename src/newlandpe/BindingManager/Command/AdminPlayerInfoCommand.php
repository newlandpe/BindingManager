<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\TelegramBot;
use pocketmine\Server;

class AdminPlayerInfoCommand implements CommandInterface {

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

        $main = Main::getInstance();
        if ($main === null) {
            $this->bot->sendMessage($chatId, "An internal error occurred.");
            return true;
        }

        $isAllowed = false;

        $admins = $main->getConfig()->get('admins', []);
        if (is_array($admins) && in_array($fromId, $admins, true)) {
            $isAllowed = true;
        } else {
            $senderPlayerName = $dataProvider->getBoundPlayerName($fromId);
            if ($senderPlayerName !== null) {
                if (Server::getInstance()->isOp($senderPlayerName)) {
                    $isAllowed = true;
                }
            }
        }

        if (!$isAllowed) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-permission-denied"));
            return true;
        }

        $telegramId = null;

        if (isset($context->message['forward_from']) && is_array($context->message['forward_from']) && isset($context->message['forward_from']['id'])) {
            $telegramId = (int)$context->message['forward_from']['id'];
        } elseif (isset($context->message['reply_to_message']) && is_array($context->message['reply_to_message']) && isset($context->message['reply_to_message']['from']) && is_array($context->message['reply_to_message']['from']) && isset($context->message['reply_to_message']['from']['id'])) {
            $telegramId = (int)$context->message['reply_to_message']['from']['id'];
        } elseif (isset($args[0]) && is_numeric($args[0])) {
            $telegramId = (int)$args[0];
        } else {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-playerinfo-usage"));
            return true;
        }

        $playerName = $dataProvider->getBoundPlayerName($telegramId);

        if ($playerName !== null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-playerinfo-success", [
                "telegram_id" => $telegramId,
                "player_name" => $playerName
            ]));
        } else {
            $this->bot->sendMessage($chatId, $lang->get("telegram-admin-playerinfo-not-found", [
                "telegram_id" => $telegramId
            ]));
        }
        return true;
    }
}

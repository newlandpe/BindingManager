<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class BindingCommand implements CommandInterface {

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
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;
        $keyboardFactory = $context->keyboardFactory;
        $args = $context->args;

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        if (!isset($args[0]) || $args[0] === '') {
            $bindingStatus = $dataProvider->getBindingStatus($fromId);
            $statusText = "";
            $buttons = [];
            switch ($bindingStatus) {
                case 2:
                    $statusText = $lang->get("binding-status-confirmed");
                    $buttons = $keyboardFactory->createBindingMenu(true);
                    break;
                case 1:
                    $statusText = $lang->get("binding-status-pending");
                    $buttons = $keyboardFactory->createBindingMenu(false);
                    break;
                default:
                    $statusText = $lang->get("binding-status-none");
                    $buttons = $keyboardFactory->createBindingMenu(false);
                    break;
            }
            $this->bot->sendMessage($chatId, $lang->get("telegram-binding-menu-message", ["status" => $statusText]), $buttons);
            return true;
        }

        $playerName = $args[0];
        $code = $dataProvider->initiateBinding($playerName, $fromId);

        if ($code === null) {
            if ($dataProvider->getBindingStatus($fromId) !== 0) {
                $this->bot->sendMessage($chatId, $lang->get('telegram-binding-already-bound'));
            } else {
                $this->bot->sendMessage($chatId, $lang->get('telegram-binding-player-already-bound'));
            }
            return true;
        }

        $this->bot->sendMessage($chatId, $lang->get("chat-binding-code", ['code' => $code]), $keyboardFactory->createCancelKeyboard());
        return true;
    }
}

<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Event\PlayerDataInfoEvent;
use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;
use pocketmine\player\Player;
use pocketmine\Server;

class MyInfoCommand implements CommandInterface {

    private TelegramBot $bot;

    public function __construct(TelegramBot $bot) {
        $this->bot = $bot;
    }

    public function execute(CommandContext $context): bool {
        if ($context->callbackQuery !== null) {
            $chatId = $context->callbackQuery['message']['chat']['id'] ?? 0;
            $fromId = $context->callbackQuery['from']['id'] ?? 0;
        } else {
            $chatId = $context->message['chat']['id'] ?? 0;
            $fromId = $context->message['from']['id'] ?? 0;
        }
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        if ($dataProvider->getBindingStatus($fromId) !== 2) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-myinfo-not-bound"));
            return true;
        }

        $playerName = $dataProvider->getBoundPlayerName($fromId);
        if ($playerName === null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-myinfo-not-bound"));
            return true;
        }

        $player = Server::getInstance()->getPlayerExact($playerName) ?? Server::getInstance()->getOfflinePlayer($playerName);

        if ($player === null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-myinfo-player-not-found"));
            return true;
        }

        $placeholders = [];
        $placeholders['nickname'] = $player->getName();

        if ($player instanceof Player) {
            $template = $lang->get("telegram-myinfo-online");
            $placeholders['status'] = $lang->get("player-info-status-online");
            $placeholders['health'] = $player->getHealth();
            $pos = $player->getPosition();
            $placeholders['position'] = round($pos->getX()) . ", " . round($pos->getY()) . ", " . round($pos->getZ());
        } else {
            $template = $lang->get("telegram-myinfo-offline");
            $placeholders['status'] = $lang->get("player-info-status-offline");
        }

        $event = new PlayerDataInfoEvent($player);
        $event->call();

        $allPlaceholders = array_merge($placeholders, $event->getPlaceholders());

        $infoText = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $matches) use ($allPlaceholders): string {
            return (string)($allPlaceholders[$matches[1]] ?? '');
        }, $template);

        // Clean up any empty lines that might result from missing placeholders
        $infoText = preg_replace('/^\s*\n/m', '', $infoText ?? '');

        if ($infoText === null) {
            $infoText = ''; // Ensure it's a string even if preg_replace fails
        }

        $this->bot->sendMessage($chatId, $infoText);
        return true;
    }
}

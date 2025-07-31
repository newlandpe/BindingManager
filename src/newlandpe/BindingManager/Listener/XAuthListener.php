<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Listener;

use Luthfi\XAuth\event\PlayerLoginEvent;
use newlandpe\BindingManager\Main;
use pocketmine\event\Listener;
use pocketmine\player\Player;

class XAuthListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $dataProvider = $this->plugin->getDataProvider();
        if ($dataProvider === null) {
            return;
        }

        $telegramId = $dataProvider->getTelegramIdByPlayerName($player->getName());
        if ($telegramId === null || !$dataProvider->isTwoFactorEnabled($telegramId)) {
            return;
        }

        $this->plugin->getFreezeManager()->freezePlayer($player);

        $bot = $this->plugin->getBot();
        if ($bot === null) {
            return;
        }

        $lang = $this->plugin->getLanguageManager();
        if ($lang === null) {
            return;
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $lang->get("2fa-keyboard-confirm"), 'callback_data' => '2fa:confirm:' . $player->getName()],
                    ['text' => $lang->get("2fa-keyboard-deny"), 'callback_data' => '2fa:deny:' . $player->getName()]
                ]
            ]
        ];

        $bot->sendMessage($telegramId, "A login attempt was detected from " . $player->getNetworkSession()->getIp() . ". Was this you?", $keyboard);
        $player->sendMessage($lang->get("2fa-prompt"));
    }
}

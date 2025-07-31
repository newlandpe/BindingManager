<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Listener;

use newlandpe\BindingManager\Event\SendNotificationEvent;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Telegram\TelegramBot;
use pocketmine\event\Listener;

class NotificationListener implements Listener {

    private ?DataProviderInterface $dataProvider = null;
    private ?TelegramBot $bot = null;

    public function __construct() {
        $main = Main::getInstance();
        if ($main === null) {
            throw new \RuntimeException('Main instance not available.');
        }
        $dataProvider = $main->getDataProvider();
        if ($dataProvider === null) {
            throw new \RuntimeException('DataProvider not available.');
        }
        $this->dataProvider = $dataProvider;

        $bot = $main->getBot();
        if ($bot === null) {
            throw new \RuntimeException('TelegramBot not available.');
        }
        $this->bot = $bot;
    }

    /**
     * @param SendNotificationEvent $event
     * @priority NORMAL
     * @ignoreCancelled false
     */
    public function onSendNotification(SendNotificationEvent $event): void {
        if ($this->dataProvider === null || $this->bot === null) {
            return;
        }
        $player = $event->getPlayer();
        $telegramId = $this->dataProvider->getTelegramIdByPlayerName($player->getName());

        if ($telegramId !== null && $this->dataProvider->areNotificationsEnabled($telegramId)) {
            $this->bot->sendMessage($telegramId, $event->getMessage());
        }
    }
}

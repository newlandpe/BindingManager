<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Event;

use newlandpe\BindingManager\Main;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\player\IPlayer;

class AccountUnboundEvent extends PluginEvent {

    private IPlayer $player;
    private int $telegramId;

    public function __construct(IPlayer $player, int $telegramId) {
        $main = Main::getInstance();
        if ($main === null) {
            throw new \RuntimeException('Main instance not available.');
        }
        parent::__construct($main);
        $this->player = $player;
        $this->telegramId = $telegramId;
    }

    public function getPlayer(): IPlayer {
        return $this->player;
    }

    public function getTelegramId(): int {
        return $this->telegramId;
    }
}

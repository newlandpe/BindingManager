<?php

declare(strict_types=1);

namespace BindingManager\Event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\player\IPlayer;
use BindingManager\Main;

class SendNotificationEvent extends PluginEvent {

    private IPlayer $player;
    private string $message;

    public function __construct(IPlayer $player, string $message) {
        $main = Main::getInstance();
        if ($main === null) {
            throw new \RuntimeException('Main instance not available.');
        }
        parent::__construct($main);
        $this->player = $player;
        $this->message = $message;
    }

    public function getPlayer(): IPlayer {
        return $this->player;
    }

    public function getMessage(): string {
        return $this->message;
    }
}

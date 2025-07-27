<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Event;

use newlandpe\BindingManager\Main;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\player\IPlayer;

class PlayerDataInfoEvent extends PluginEvent {

    private IPlayer $player;
    /** @var array<string, string> */
    private array $placeholders = [];

    public function __construct(IPlayer $player) {
        $main = Main::getInstance();
        if ($main === null) {
            throw new \RuntimeException('Main instance not available.');
        }
        parent::__construct($main);
        $this->player = $player;
        $this->placeholders = [];
    }

    public function getPlayer(): IPlayer {
        return $this->player;
    }

    public function addPlaceholder(string $key, string $value): void {
        $this->placeholders[$key] = $value;
    }

    /**
     * @return array<string, string>
     */
    public function getPlaceholders(): array {
        return $this->placeholders;
    }
}

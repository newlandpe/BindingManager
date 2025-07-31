<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Service;

use pocketmine\player\Player;

class FreezeManager {

    /** @var array<string, bool> */
    private array $frozenPlayers = [];

    public function freezePlayer(Player $player): void {
        $this->frozenPlayers[strtolower($player->getName())] = true;
    }

    public function unfreezePlayer(Player $player): void {
        unset($this->frozenPlayers[strtolower($player->getName())]);
    }

    public function isPlayerFrozen(Player $player): bool {
        return isset($this->frozenPlayers[strtolower($player->getName())]);
    }
}

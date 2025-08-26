<?php

/*
 *
 *  ____  _           _ _             __  __
 * | __ )(_)_ __   __| (_)_ __   __ _|  \/  | __ _ _ __   __ _  __ _  ___ _ __
 * |  _ \| | '_ \ / _` | | '_ \ / _` | |\/| |/ _` | '_ \ / _` |/ _` |/ _ \ '__|
 * | |_) | | | | | (_| | | | | | (_| | |  | | (_| | | | | (_| | (_| |  __/ |
 * |____/|_|_| |_|\__,_|_|_| |_|\__, |_|  |_|\__,_|_| |_|\__,_|\__, |\___|_|
 *                              |___/                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace newlandpe\BindingManager\Event;

use pocketmine\event\plugin\PluginEvent;
use pocketmine\player\IPlayer;
use pocketmine\plugin\Plugin;

class PlayerDataInfoEvent extends PluginEvent {

    private IPlayer $player;
    /** @var array<string, string> */
    private array $placeholders = [];

    public function __construct(IPlayer $player, Plugin $plugin) {
        parent::__construct($plugin);
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

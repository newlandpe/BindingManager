<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Listener;

use newlandpe\BindingManager\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

class FreezeListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($player)) {
            $langManager = $this->plugin->getLanguageManager();
            if ($langManager !== null) {
                $player->sendMessage($langManager->get("2fa-command-not-allowed"));
            }
            $event->cancel();
        }
    }

    /**
     * @param PlayerChatEvent $event
     */
    public function onPlayerChat(PlayerChatEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $langManager = $this->plugin->getLanguageManager();
            if ($langManager !== null) {
                $event->getPlayer()->sendMessage($langManager->get("2fa-chat-not-allowed"));
            }
            $event->cancel();
        }
    }

    public function onPlayerInteract(PlayerInteractEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerDropItem(PlayerDropItemEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && $this->plugin->getFreezeManager()->isPlayerFrozen($entity)) {
            $event->cancel();
        }
    }
}

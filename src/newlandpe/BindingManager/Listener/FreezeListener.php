<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Listener;

use newlandpe\BindingManager\Main;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBedEnterEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerToggleFlightEvent;
use pocketmine\event\player\PlayerToggleSneakEvent;
use pocketmine\event\player\PlayerToggleSprintEvent;
use pocketmine\event\server\CommandEvent;
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
    public function onPlayerCommand(CommandEvent $event): void {
        $sender = $event->getSender();
        if (!$sender instanceof Player) {
            return;
        }
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($sender)) {
            $langManager = $this->plugin->getLanguageManager();
            if ($langManager !== null) {
                $sender->sendMessage($langManager->get("2fa-command-not-allowed"));
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

    public function onBlockBreak(BlockBreakEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerItemUse(PlayerItemUseEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerItemConsume(PlayerItemConsumeEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerBedEnter(PlayerBedEnterEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerDeath(PlayerDeathEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerToggleFlight(PlayerToggleFlightEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerToggleSneak(PlayerToggleSneakEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlayerToggleSprint(PlayerToggleSprintEvent $event): void {
        if ($this->plugin->getFreezeManager()->isPlayerFrozen($event->getPlayer())) {
            $event->cancel();
        }
    }
}

<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Task;

use newlandpe\BindingManager\Main;
use pocketmine\scheduler\Task;

class TwoFACleanupTask extends Task {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $twoFAManager = $this->plugin->getTwoFAManager();

        if ($twoFAManager !== null) {
            $twoFAManager->cleanupExpiredRequests($this->plugin);
        }
    }
}

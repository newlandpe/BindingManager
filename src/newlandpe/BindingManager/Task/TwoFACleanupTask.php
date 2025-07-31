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
        $bot = $this->plugin->getBot();
        $lang = $this->plugin->getLanguageManager();

        if ($twoFAManager !== null && $bot !== null && $lang !== null) {
            $twoFAManager->cleanupExpiredRequests($bot, $lang);
        }
    }
}

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
        $twoFactorAuthService = $this->plugin->getTwoFactorAuthService();

        if ($twoFactorAuthService !== null) {
            $twoFactorAuthService->cleanupExpiredRequests($this->plugin);
        }
    }
}

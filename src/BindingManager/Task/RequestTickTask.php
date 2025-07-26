<?php

declare(strict_types=1);

namespace BindingManager\Task;

use pocketmine\scheduler\Task;
use BindingManager\AsyncRequestManager;

class RequestTickTask extends Task {

    public function onRun(): void {
        AsyncRequestManager::getInstance()->tick();
    }
}

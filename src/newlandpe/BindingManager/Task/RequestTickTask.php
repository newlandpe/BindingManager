<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Task;

use newlandpe\BindingManager\AsyncRequestManager;
use pocketmine\scheduler\Task;

class RequestTickTask extends Task {

    public function onRun(): void {
        AsyncRequestManager::getInstance()->tick();
    }
}

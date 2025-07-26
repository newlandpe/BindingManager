<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;

use BindingManager\Command\CommandContext;

interface CommandInterface {

    public function execute(CommandContext $context): bool;
}

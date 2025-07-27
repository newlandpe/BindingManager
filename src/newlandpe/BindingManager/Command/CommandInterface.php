<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Command\CommandContext;
use newlandpe\BindingManager\Factory\KeyboardFactory;
use Bnewlandpe\indingManager\LanguageManager;
use newlandpe\BindingManager\Provider\DataProviderInterface;

interface CommandInterface {

    public function execute(CommandContext $context): bool;
}

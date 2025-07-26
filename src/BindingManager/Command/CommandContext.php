<?php

declare(strict_types=1);

namespace BindingManager\Command;

use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class CommandContext {

    public function __construct(
        public TelegramBot $bot,
        public LanguageManager $lang,
        public DataProviderInterface $dataProvider,
        public KeyboardFactory $keyboardFactory,
        public array $message,
        public array $args,
        public ?array $callbackQuery = null
    ) {}
}

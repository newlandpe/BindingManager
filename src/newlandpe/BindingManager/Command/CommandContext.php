<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\TelegramBot;

class CommandContext {

    public function __construct(
        public TelegramBot $bot,
        public LanguageManager $lang,
        public DataProviderInterface $dataProvider,
        public KeyboardFactory $keyboardFactory,
        /** @var array<string, mixed> */
        public array $message,
        /** @var string[] */
        public array $args,
        /** @var array<string, mixed>|null */
        public ?array $callbackQuery = null
    ) {}
}

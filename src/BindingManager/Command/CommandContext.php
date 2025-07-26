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
        /** @var array<string, mixed> */
        public array $message,
        /** @var string[] */
        public array $args,
        /** @var array<string, mixed>|null */
        public ?array $callbackQuery = null
    ) {}
}

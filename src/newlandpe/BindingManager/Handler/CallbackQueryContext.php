<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Handler;

use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\TelegramBot;

class CallbackQueryContext {

    public function __construct(
        public TelegramBot $bot,
        public LanguageManager $lang,
        public DataProviderInterface $dataProvider,
        public KeyboardFactory $keyboardFactory,
        /** @var array<string, mixed> */
        public array $callbackQuery
    ) {}
}

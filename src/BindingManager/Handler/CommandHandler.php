<?php

declare(strict_types=1);

namespace BindingManager\Handler;

use BindingManager\Command\AdminPlayerInfoCommand;
use BindingManager\Command\BindingCommand;
use BindingManager\Command\CommandContext;
use BindingManager\Command\CommandInterface;
use BindingManager\Command\HelpCommand;
use BindingManager\Command\MyInfoCommand;
use BindingManager\Command\ResetBindingCommand;
use BindingManager\Command\StartCommand;
use BindingManager\Command\UnbindCommand;
use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class CommandHandler {

    private TelegramBot $bot;
    private KeyboardFactory $keyboardFactory;
    /** @var array<string, CommandInterface> */
    private array $commands;

    /**
     * @param TelegramBot $bot
     * @param KeyboardFactory $keyboardFactory
     */
    public function __construct(TelegramBot $bot, KeyboardFactory $keyboardFactory) {
        $this->bot = $bot;
        $this->keyboardFactory = $keyboardFactory;

        $this->commands = [
            'start' => new StartCommand($bot),
            'help' => new HelpCommand($bot),
            'binding' => new BindingCommand($bot),
            'myinfo' => new MyInfoCommand($bot),
            'unbind' => new UnbindCommand($bot),
            'admininfo' => new AdminPlayerInfoCommand($bot),
            'reset' => new ResetBindingCommand($bot),
        ];
    }

    /**
     * @param string $name
     * @return CommandInterface|null
     */
    public function findCommand(string $name): ?CommandInterface {
        return $this->commands[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $message
     * @param LanguageManager $lang
     * @param DataProviderInterface $dataProvider
     */
    public function handle(array $message, LanguageManager $lang, DataProviderInterface $dataProvider): void {
        $text = $message['text'] ?? '';
        if ($text === '' || $text[0] !== '/') {
            return;
        }

        [$commandFull, $argString] = explode(' ', $text . ' ', 2);
        $args = trim($argString) !== '' ? explode(' ', trim($argString)) : [];

        [$commandNameRaw, $targetBot] = array_pad(explode('@', $commandFull, 2), 2, null);
        $commandName = ltrim($commandNameRaw, '/');

        if ($targetBot !== null && strtolower($targetBot) !== strtolower($this->bot->getUsername())) {
            return;
        }

        $chat = is_array($message['chat']) ? $message['chat'] : [];
        $chatId = (int) ($chat['id'] ?? 0);
        if ($chatId === 0) return;

        if (($chat['type'] ?? 'private') !== 'private' && $targetBot === null) {
            return;
        }

        if (($chat['type'] ?? 'private') !== 'private') {
            $this->bot->sendMessage($chatId, $lang->get("telegram-command-private-only"));
            return;
        }

        $command = $this->commands[$commandName] ?? null;
        if ($command !== null) {
            $context = new CommandContext($this->bot, $lang, $dataProvider, $this->keyboardFactory, $message, $args);
            $command->execute($context);
        } elseif ($targetBot === null) {
            $this->bot->sendMessage($chatId, $lang->get("telegram-unknown-command"));
        }
    }
}

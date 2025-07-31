<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Handler;

use newlandpe\BindingManager\Command\AdminPlayerInfoCommand;
use newlandpe\BindingManager\Command\BindingCommand;
use newlandpe\BindingManager\Command\CommandContext;
use newlandpe\BindingManager\Command\CommandInterface;
use newlandpe\BindingManager\Command\HelpCommand;
use newlandpe\BindingManager\Command\MyInfoCommand;
use newlandpe\BindingManager\Command\ResetBindingCommand;
use newlandpe\BindingManager\Command\StartCommand;
use newlandpe\BindingManager\Command\TwoFactorCommand;
use newlandpe\BindingManager\Command\UnbindCommand;
use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\TelegramBot;

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
            '2fa' => new TwoFactorCommand($bot),
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
        $text = $message['text'] ?? null;
        if (!is_string($text)) {
            return;
        }
        if ($text === '') {
            return;
        }
        if ($text[0] !== '/') {
            return;
        }

        /** @var array{0: string, 1: string} $explodedText */
        $explodedText = array_pad(explode(' ', $text, 2), 2, '');
        $commandFull = $explodedText[0];
        $argString = $explodedText[1];

        $args = [];
        if (trim($argString) !== '') {
            $args = explode(' ', trim($argString));
        }

        /** @var array{0: string, 1: string} $explodedCommand */
        $explodedCommand = array_pad(explode('@', $commandFull, 2), 2, '');
        $commandNameRaw = $explodedCommand[0];
        $targetBot = $explodedCommand[1] !== '' ? $explodedCommand[1] : null;
        $commandName = ltrim($commandNameRaw, '/');

        if ($targetBot !== null && strtolower($targetBot) !== strtolower($this->bot->getUsername())) {
            return;
        }

        $chat = (isset($message['chat']) && is_array($message['chat'])) ? $message['chat'] : [];
        $chatId = (int)($chat['id'] ?? 0);
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

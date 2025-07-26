<?php

declare(strict_types=1);

namespace BindingManager\Handler;

use BindingManager\Command\CommandContext;
use BindingManager\Factory\KeyboardFactory;
use BindingManager\LanguageManager;
use BindingManager\Main;
use BindingManager\Provider\DataProviderInterface;
use BindingManager\TelegramBot;

class CallbackQueryHandler {

    private TelegramBot $bot;
    private KeyboardFactory $keyboardFactory;

    public function __construct(TelegramBot $bot, KeyboardFactory $keyboardFactory) {
        $this->bot = $bot;
        $this->keyboardFactory = $keyboardFactory;
    }

    /**
     * @param array<string, mixed> $callbackQuery
     * @param LanguageManager $lang
     * @param DataProviderInterface $dataProvider
     */
    public function handle(array $callbackQuery, LanguageManager $lang, DataProviderInterface $dataProvider): void {
        $message = $callbackQuery['message'] ?? null;
        /** @var array<string, mixed>|null $message */
        if ($message === null) {
            return; // No message associated with the callback
        }

        // --- SECURITY: Ensure callbacks are only handled in private chats ---
        if (($message['chat']['type'] ?? 'private') !== 'private') {
            $callbackQueryId = $callbackQuery['id'] ?? null;
            if ($callbackQueryId !== null) {
                $this->bot->request('answerCallbackQuery', [
                    'callback_query_id' => $callbackQueryId,
                    'text' => $lang->get("telegram-command-private-only"),
                    'show_alert' => true
                ]);
            }
            return;
        }
        // --- END SECURITY ---

        if (($message['from']['id'] ?? null) !== $this->bot->getId()) {
            return; // Not for us
        }

        $data = $callbackQuery['data'] ?? null;
        if ($data === null) {
            return; // No data
        }

        $explodedData = explode(':', (string) $data);
        if (count($explodedData) < 2) {
            return; // Invalid data format
        }

        $menu = $explodedData[0];
        $action = $explodedData[1];

        // Acknowledge the callback query only if we understand the data format
        $callbackQueryId = $callbackQuery['id'] ?? null;
        if ($callbackQueryId !== null) {
            $this->bot->request('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
        }

        $context = new CallbackQueryContext($this->bot, $lang, $dataProvider, $this->keyboardFactory, $callbackQuery);

        switch ($menu) {
            case 'menu':
                $this->handleMainMenu($context, $action);
                break;
            case 'binding':
                $this->handleBindingMenu($context, $action);
                break;
            // No default case: we simply ignore unknown menus
        }
    }

    private function handleMainMenu(CallbackQueryContext $context, string $action): void {
        $chatId = $context->callbackQuery['message']['chat']['id'] ?? 0;
        $fromId = $context->callbackQuery['from']['id'] ?? 0;
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;
        $bot = $context->bot;
        $keyboardFactory = $context->keyboardFactory;

        switch ($action) {
            case 'bind':
                $commandHandler = $bot->getCommandHandler();
                $command = $commandHandler->findCommand('binding');
                if ($command !== null) {
                    $commandContext = new CommandContext($bot, $lang, $dataProvider, $keyboardFactory, ['chat' => ['id' => $chatId], 'from' => ['id' => $fromId]], [], $context->callbackQuery);
                    $command->execute($commandContext);
                }
                break;
            case 'help':
                $bot->sendMessage($chatId, $lang->get('telegram-help'));
                break;
        }
    }

    /**
     * @param CallbackQueryContext $context
     */
    private function handleBindingMenu(CallbackQueryContext $context, string $action): void {
        $chatId = $context->callbackQuery['message']['chat']['id'] ?? 0;
        $fromId = $context->callbackQuery['from']['id'] ?? 0;
        $lang = $context->lang;
        $dataProvider = $context->dataProvider;
        $bot = $context->bot;
        $keyboardFactory = $context->keyboardFactory;

        switch ($action) {
            case 'bind':
                $main = Main::getInstance();
                if ($main !== null) {
                    $main->setUserState($fromId, 'awaiting_nickname');
                    $bot->sendMessage($chatId, $lang->get('telegram-enter-nickname'));
                }
                break;
            case 'myinfo':
                $commandHandler = $bot->getCommandHandler();
                $command = $commandHandler->findCommand('myinfo');
                if ($command !== null) {
                    $commandContext = new CommandContext($bot, $lang, $dataProvider, $keyboardFactory, $context->callbackQuery['message'], [], $context->callbackQuery);
                    $command->execute($commandContext);
                }
                break;
            case 'unbind':
                $commandHandler = $bot->getCommandHandler();
                $command = $commandHandler->findCommand('unbind');
                if ($command !== null) {
                    $commandContext = new CommandContext($bot, $lang, $dataProvider, $keyboardFactory, $context->callbackQuery['message'], [], $context->callbackQuery);
                    $command->execute($commandContext);
                }
                break;
            case 'notifications':
                $isEnabled = $dataProvider->toggleNotifications($fromId);
                $status = $isEnabled ? 'enabled' : 'disabled';
                $bot->sendMessage($chatId, $lang->get("telegram-notifications-status-changed-{$status}"));
                break;
            case 'cancel':
                $messageId = $context->callbackQuery['message']['message_id'] ?? null;
                if ($messageId !== null) {
                    $dataProvider->unbindByTelegramId($fromId);
                    $bot->editMessageText($chatId, $messageId, $lang->get('telegram-binding-cancelled'));
                }
                break;
        }
    }
}

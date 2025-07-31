<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Handler;

use Luthfi\XAuth\Main as XAuthMain;
use newlandpe\BindingManager\Command\CommandContext;
use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\Handler\CommandHandler;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Service\TwoFAManager;
use pocketmine\Server;

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
        if (!isset($message['chat']) || !is_array($message['chat']) || ($message['chat']['type'] ?? 'private') !== 'private') {
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

        if (!isset($message['from']) || !is_array($message['from']) || ($message['from']['id'] ?? null) !== $this->bot->getId()) {
            return; // Not for us
        }

        $data = $callbackQuery['data'] ?? null;
        if (!is_string($data)) {
            return; // No data or invalid data type
        }

        $explodedData = explode(':', $data);
        if (count($explodedData) < 2) {
            return; // Invalid data format
        }

        $menu = $explodedData[0] ?? '';
        $action = $explodedData[1] ?? '';

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
            case 'unbind':
                $this->handleUnbindMenu($context, $action);
                break;
            case '2fa':
                $playerName = $explodedData[2] ?? '';
                $this->handle2FAMenu($context, $action, $playerName);
                break;
            // No default case: we simply ignore unknown menus
        }
    }

    private function handleMainMenu(CallbackQueryContext $context, string $action): void {
        $chatId = 0;
        if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message']) && isset($context->callbackQuery['message']['chat']) && is_array($context->callbackQuery['message']['chat'])) {
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        }

        $fromId = 0;
        if (isset($context->callbackQuery['from']) && is_array($context->callbackQuery['from'])) {
            $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
        }
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
        $chatId = 0;
        if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message']) && isset($context->callbackQuery['message']['chat']) && is_array($context->callbackQuery['message']['chat'])) {
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        }

        $fromId = 0;
        if (isset($context->callbackQuery['from']) && is_array($context->callbackQuery['from'])) {
            $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
        }
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
                    $message = (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) ? $context->callbackQuery['message'] : [];
                    $commandContext = new CommandContext($bot, $lang, $dataProvider, $keyboardFactory, $message, [], $context->callbackQuery);
                    $command->execute($commandContext);
                }
                break;
            case 'unbind':
                $commandHandler = $bot->getCommandHandler();
                $command = $commandHandler->findCommand('unbind');
                if ($command !== null) {
                    $message = (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) ? $context->callbackQuery['message'] : [];
                    $commandContext = new CommandContext($bot, $lang, $dataProvider, $keyboardFactory, $message, [], $context->callbackQuery);
                    $command->execute($commandContext);
                }
                break;
            case 'notifications':
                $isEnabled = $this->bindingService->toggleNotifications($fromId);
                $status = $isEnabled ? 'enabled' : 'disabled';
                $bot->sendMessage($chatId, $lang->get("telegram-notifications-status-changed-{$status}"));
                break;
            case 'cancel':
                $messageId = 0;
                if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) {
                    $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
                }

                if ($messageId !== 0) {
                    $main = Main::getInstance();
                    if ($main !== null) {
                        $main->setUserState($fromId, null); // Reset state
                        $bot->editMessageText($chatId, $messageId, $lang->get('telegram-binding-cancelled'));
                    }
                }
                break;
        }
    }

    /**
     * @param CallbackQueryContext $context
     */
    private function handleUnbindMenu(CallbackQueryContext $context, string $action): void {
        $chatId = 0;
        if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message']) && isset($context->callbackQuery['message']['chat']) && is_array($context->callbackQuery['message']['chat'])) {
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        }

        $fromId = 0;
        if (isset($context->callbackQuery['from']) && is_array($context->callbackQuery['from'])) {
            $fromId = (int)($context->callbackQuery['from']['id'] ?? 0);
        }
        $lang = $context->lang;
        $bot = $context->bot;
        $keyboardFactory = $context->keyboardFactory;

        switch ($action) {
            case 'cancel':
                $messageId = 0;
                if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) {
                    $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
                }

                if ($messageId !== 0) {
                    $main = Main::getInstance();
                    if ($main !== null) {
                        $main->setUserState($fromId, null); // Reset state
                        $bot->editMessageText($chatId, $messageId, $lang->get('telegram-unbind-cancelled'));
                    }
                }
                break;
        }
    }

    private function handle2FAMenu(CallbackQueryContext $context, string $action, string $playerName): void {
        $player = Server::getInstance()->getPlayerExact($playerName);
        if ($player === null) {
            return;
        }

        $main = Main::getInstance();
        if ($main === null) {
            return;
        }

        $twoFactorAuthService = $main->getTwoFactorAuthService();
        if ($twoFactorAuthService === null) {
            return;
        }

        $explodedData = explode(':', $context->callbackQuery['data']);
        $code = $explodedData[3] ?? '';

        $request = $twoFactorAuthService->getRequest($playerName);

        if ($request === null || $request['code'] !== $code) {
            // Invalid or expired request, or code mismatch
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
            $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
            if ($chatId !== 0 && $messageId !== 0) {
                $this->bot->editMessageText($chatId, $messageId, $main->getLanguageManager()->get("2fa-login-invalid-code"));
            }
            return;
        }

        $twoFactorAuthService->removeRequest($playerName);

        if ($action === 'confirm') {
            $twoFactorAuthService->unfreezePlayer($player);
            $player->sendMessage($main->getLanguageManager()->get("2fa-login-confirmed"));
            $xauth = Server::getInstance()->getPluginManager()->getPlugin('XAuth');
            if ($xauth instanceof XAuthMain) {
                $xauth->forceLogin($player);
            }
        } elseif ($action === 'deny') {
            $player->kick($main->getLanguageManager()->get("2fa-login-denied"));
        }

        $chatId = 0;
        if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message']) && isset($context->callbackQuery['message']['chat']) && is_array($context->callbackQuery['message']['chat'])) {
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        }
        $messageId = 0;
        if (isset($context->callbackQuery['message']) && is_array($context->callbackQuery['message'])) {
            $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
        }

        if ($chatId !== 0 && $messageId !== 0) {
            $this->bot->editMessageText($chatId, $messageId, $main->getLanguageManager()->get("2fa-selection-made"));
        }
    }
}

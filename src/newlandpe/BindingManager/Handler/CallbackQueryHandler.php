<?php

/*
 *
 *  ____  _           _ _             __  __
 * | __ )(_)_ __   __| (_)_ __   __ _|  \/  | __ _ _ __   __ _  __ _  ___ _ __
 * |  _ \| | '_ \ / _` | | '_ \ / _` | |\/| |/ _` | '_ \ / _` |/ _` |/ _ \ '__|
 * | |_) | | | | | (_| | | | | | (_| | |  | | (_| | | | | (_| | (_| |  __/ |
 * |____/|_|_| |_|\__,_|_|_| |_|\__, |_|  |_|\__,_|_| |_|\__,_|\__, |\___|_|
 *                              |___/                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author Sergiy Chernega
 * @link https://chernega.eu.org/
 *
 *
 */

declare(strict_types=1);

namespace newlandpe\BindingManager\Handler;

use newlandpe\BindingManager\Command\CommandContext;
use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Service\TwoFactorAuthService;
use newlandpe\BindingManager\Service\UserStateManager;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\Server;
use pocketmine\plugin\PluginManager;

class CallbackQueryHandler {

    private ServiceContainer $container;

    public function __construct(ServiceContainer $container) {
        $this->container = $container;
    }

    /**
     * @param array<string, mixed> $callbackQuery
     */
    public function handle(array $callbackQuery): void {
        $message = $callbackQuery['message'] ?? null;
        if ($message === null) return;

        $bot = $this->container->get(TelegramBot::class);
        $lang = $this->container->get(LanguageManager::class);

        // --- SECURITY: Ensure callbacks are only handled in private chats ---
        if (($message['chat']['type'] ?? 'private') !== 'private') {
            $bot->request('answerCallbackQuery', ['callback_query_id' => $callbackQuery['id'], 'text' => $lang->get("telegram-command-private-only"), 'show_alert' => true]);
            return;
        }
        // --- END SECURITY ---

        $fromId = (int)($callbackQuery['from']['id'] ?? 0);
        if ($fromId === 0) return; // Invalid sender ID

        $data = $callbackQuery['data'] ?? null;
        if (!is_string($data)) return;

        $explodedData = explode(':', $data);
        $menu = $explodedData[0] ?? '';
        $action = $explodedData[1] ?? '';
        $playerName = $explodedData[2] ?? ''; // Used for account-specific actions

        $bot->request('answerCallbackQuery', ['callback_query_id' => $callbackQuery['id']]);

        $context = new CommandContext(
            $callbackQuery['message'],
            [], // args are set later using setArgs
            $callbackQuery
        );

        switch ($menu) {
            case 'menu':
                $this->handleMainMenu($context, $action, $fromId);
                break;
            case 'binding':
                $this->handleBindingMenu($context, $action, $fromId);
                break;
            case 'account':
                $this->handleAccountMenu($context, $action, $fromId, $playerName);
                break;
            case '2fa': // This case is for 2FA login confirmation, not toggling
                $this->handle2FAMenu($context, $action, $playerName);
                break;
        }
    }

    private function handleMainMenu(CommandContext $context, string $action, int $fromId): void {
        $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
        $bot = $this->container->get(TelegramBot::class);
        $lang = $this->container->get(LanguageManager::class);
        $keyboardFactory = $this->container->get(KeyboardFactory::class);
        $bindingService = $this->container->get(BindingService::class);

        switch ($action) {
            case 'help':
                $bot->sendMessage($chatId, $lang->get('telegram-help'));
                break;
            case 'start': // Handle /start command from Telegram
                // Redirect to binding menu to show accounts, which is now menu:binding
                $this->handleMainMenu($context, 'binding', $fromId);
                break;
            case 'initial':
                $keyboard = $keyboardFactory->createInitialMenu();
                $bot->editMessageText($chatId, $messageId, $lang->get('telegram-binding-menu-message'), $keyboard);
                break;
            case 'binding': // This action is used to display the main binding menu (list of accounts)
                $playerNames = $bindingService->getBoundPlayerNames($fromId);
                $keyboard = $keyboardFactory->createAccountListMenu($playerNames);
                if (empty($playerNames)) {
                    $bot->editMessageText($chatId, $messageId, $lang->get('telegram-no-accounts-bound'), $keyboard);
                } else {
                    $bot->editMessageText($chatId, $messageId, $lang->get('telegram-select-account'), $keyboard);
                }
                break;
        }
    }

    private function handleBindingMenu(CommandContext $context, string $action, int $fromId): void {
        $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
        $bot = $this->container->get(TelegramBot::class);
        $lang = $this->container->get(LanguageManager::class);

        switch ($action) {
            case 'bind':
                $this->container->get(UserStateManager::class)->setUserState($fromId, 'awaiting_nickname');
                $bot->sendMessage($chatId, $lang->get('telegram-enter-nickname'));
                break;
            case 'cancel':
                $this->container->get(UserStateManager::class)->setUserState($fromId, null); // Reset state
                $data = $context->callbackQuery['data'] ?? null;
                $explodedData = explode(':', $data);
                $code = $explodedData[2] ?? null; // Extract the code from callback data
                if ($code !== null) {
                    $this->container->get(BindingService::class)->deleteTemporaryBinding($code); // Delete the specific temporary binding
                }
                if ($messageId !== 0) {
                    $bot->editMessageText($chatId, $messageId, $lang->get('telegram-binding-cancelled'));
                } else {
                    $bot->sendMessage($chatId, $lang->get('telegram-binding-cancelled'));
                }
                break;
        }
    }

    private function handleUnbindMenu(CommandContext $context, string $action, int $fromId): void {
        $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
        $bot = $this->container->get(TelegramBot::class);
        $lang = $this->container->get(LanguageManager::class);

        switch ($action) {
            case 'cancel':
                $this->container->get(UserStateManager::class)->setUserState($fromId, null); // Reset state
                if ($messageId !== 0) {
                    $bot->editMessageText($chatId, $messageId, $lang->get('telegram-unbind-cancelled'));
                } else {
                    $bot->sendMessage($chatId, $lang->get('telegram-unbind-cancelled'));
                }
                break;
        }
    }

    private function handleAccountMenu(CommandContext $context, string $action, int $fromId, string $playerName): void {
        $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
        $bot = $this->container->get(TelegramBot::class);
        $lang = $this->container->get(LanguageManager::class);
        $keyboardFactory = $this->container->get(KeyboardFactory::class);
        $bindingService = $this->container->get(BindingService::class);
        $twoFactorAuthService = $this->container->get(TwoFactorAuthService::class);

        // Ensure the player name belongs to the current Telegram user
        $boundPlayerNames = $bindingService->getBoundPlayerNames($fromId);
        if (!in_array($playerName, $boundPlayerNames, true)) {
            $bot->sendMessage($chatId, $lang->get('telegram-account-not-found'));
            return;
        }

        switch ($action) {
            case 'select':
                $notificationsEnabled = $bindingService->areNotificationsEnabled($playerName);
                $twoFaEnabled = $twoFactorAuthService->get2FAStatus($playerName);
                $keyboard = $keyboardFactory->createAccountManagementMenu($playerName, $notificationsEnabled, $twoFaEnabled);
                $bot->editMessageText($chatId, $messageId, $lang->get('telegram-manage-account', ['player' => $playerName]), $keyboard);
                break;
            case 'info':
                $command = $bot->getCommandHandler()->findCommand('myinfo');
                if ($command !== null) {
                    $context->setArgs([$playerName]); // Set player name as argument
                    $command->execute($context);
                }
                break;
            case 'notifications':
                $isEnabled = $bindingService->toggleNotifications($playerName);
                $status = $isEnabled ? 'enabled' : 'disabled';
                $bot->sendMessage($chatId, $lang->get("telegram-notifications-status-changed-{$status}", ['player' => $playerName]));
                // Re-display account management menu with updated status
                $notificationsEnabled = $bindingService->areNotificationsEnabled($playerName);
                $twoFaEnabled = $twoFactorAuthService->get2FAStatus($playerName);
                $keyboard = $keyboardFactory->createAccountManagementMenu($playerName, $notificationsEnabled, $twoFaEnabled);
                $bot->editMessageReplyMarkup($chatId, $messageId, $keyboard);
                break;
            case '2fa':
                $isEnabled = $twoFactorAuthService->toggle2FA($playerName);
                $status = $isEnabled ? 'enabled' : 'disabled';
                $bot->sendMessage($chatId, $lang->get("2fa-status-{$status}", ['player' => $playerName]));
                // Re-display account management menu with updated status
                $notificationsEnabled = $bindingService->areNotificationsEnabled($playerName);
                $twoFaEnabled = $twoFactorAuthService->get2FAStatus($playerName);
                $keyboard = $keyboardFactory->createAccountManagementMenu($playerName, $notificationsEnabled, $twoFaEnabled);
                $bot->editMessageReplyMarkup($chatId, $messageId, $keyboard);
                break;
            case 'unbind':
                $command = $bot->getCommandHandler()->findCommand('unbind');
                if ($command !== null) {
                    $context->setArgs([$playerName]); // Set player name as argument
                    $command->execute($context);
                }
                break;
        }
    }

    private function handle2FAMenu(CommandContext $context, string $action, string $playerName): void {
        $player = $this->container->get(Server::class)->getPlayerExact($playerName);
        if ($player === null) return;

        $twoFactorAuthService = $this->container->get(TwoFactorAuthService::class);
        $lang = $this->container->get(LanguageManager::class);
        $bot = $this->container->get(TelegramBot::class);

        $explodedData = explode(':', $context->callbackQuery['data']);
        $code = $explodedData[3] ?? '';

        $request = $twoFactorAuthService->getRequest($playerName);

        if ($request === null || $request['code'] !== $code) {
            $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
            $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);
            if ($chatId !== 0 && $messageId !== 0) {
                $bot->editMessageText($chatId, $messageId, $lang->get("2fa-login-invalid-code"));
            }
            return;
        }

        $twoFactorAuthService->removeRequest($playerName);

        if ($action === 'confirm') {
            $player->sendMessage($lang->get("2fa-login-confirmed"));
            $xauth = $this->container->get(PluginManager::class)->getPlugin('XAuth');
            if ($xauth instanceof \Luthfi\XAuth\Main) {
                $xauth->getAuthenticationFlowManager()->completeStep($player, "binding_manager_2fa");
            }
        } elseif ($action === 'deny') {
            $player->kick($lang->get("2fa-login-denied"));
        }

        $chatId = (int)($context->callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int)($context->callbackQuery['message']['message_id'] ?? 0);

        if ($chatId !== 0 && $messageId !== 0) {
            $bot->editMessageText($chatId, $messageId, $lang->get("2fa-selection-made"));
        }
    }
}

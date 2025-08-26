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

namespace newlandpe\BindingManager\Service;

use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Telegram\TelegramBot;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class TwoFactorAuthService {

    private TelegramBot $bot;
    private LanguageManager $languageManager;
    private BindingService $bindingService;
    private Config $config;
    private Main $plugin;

    /** @var array<string, array{chat_id: int, message_id: int, code: string, expiry: int}> */
    private array $activeRequests = [];

    public function __construct(TelegramBot $bot, LanguageManager $languageManager, BindingService $bindingService, Config $config, Main $plugin) {
        $this->bot = $bot;
        $this->languageManager = $languageManager;
        $this->bindingService = $bindingService;
        $this->config = $config;
        $this->plugin = $plugin;
    }

    public function start2FAProcess(Player $player): void {
        $playerName = $player->getName();
        if (!$this->bindingService->isTwoFactorEnabled($playerName)) {
            return; // 2FA not enabled for this player
        }

        // Conditional logic based on twoFactorMode
        $twoFactorMode = $this->config->get('two-factor-mode', 'after_password');
        if ($twoFactorMode === 'after_password') {
            // If mode is 'after_password', only proceed if it was a manual login
            $xAuthPlugin = $this->plugin->getServer()->getPluginManager()->getPlugin('XAuth');
            if ($xAuthPlugin instanceof \Luthfi\XAuth\Main) {
                $authFlowManager = $xAuthPlugin->getAuthenticationFlowManager();
                $status = $authFlowManager->getPlayerAuthenticationStepStatus($player, "xauth_login");
                if ($status === null) { // If xauth_login step is not completed
                    // If xauth_login step is not completed, do not start 2FA process
                    return;
                }
            } else {
                // If XAuth plugin is not found or not an instance of \Luthfi\XAuth\Main,
                // we should probably log an error or handle this case appropriately.
                // For now, we'll assume it should not proceed with 2FA if XAuth is not properly loaded.
                return;
            }
        }

        $telegramId = $this->bindingService->getTelegramIdByPlayerName($playerName);
        if ($telegramId === null) {
            return; // Player is not bound
        }

        $code = $this->generateUniqueCode();
        $timeout = $this->config->get('2fa-timeout-seconds', 120);
        $expiry = time() + (int)$timeout;

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $this->languageManager->get('2fa-keyboard-confirm'), 'callback_data' => '2fa:confirm:' . $playerName . ':' . $code],
                    ['text' => $this->languageManager->get('2fa-keyboard-deny'), 'callback_data' => '2fa:deny:' . $playerName . ':' . $code]
                ]
            ]
        ];

        $message = $this->languageManager->get('2fa-login-attempt', [
            'player_name' => $playerName,
            'ip' => $player->getNetworkSession()->getIp()
        ]);

        $sentMessage = $this->bot->sendMessage($telegramId, $message, $keyboard);

        if ($sentMessage !== null) {
            $this->addRequest($playerName, $telegramId, $sentMessage, $code, $expiry);
            $player->sendMessage($this->languageManager->get('2fa-prompt'));
        }
    }

    public function addRequest(string $playerName, int $chatId, int $messageId, string $code, int $expiry): void {
        $this->activeRequests[strtolower($playerName)] = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'code' => $code,
            'expiry' => $expiry
        ];
    }

    public function getRequest(string $playerName): ?array {
        $playerName = strtolower($playerName);
        if (isset($this->activeRequests[$playerName])) {
            $request = $this->activeRequests[$playerName];
            if (time() > $request['expiry']) {
                $this->removeRequest($playerName);
                return null; // Request expired
            }
            return $request;
        }
        return null;
    }

    public function removeRequest(string $playerName): void {
        unset($this->activeRequests[strtolower($playerName)]);
    }

    public function getAllRequests(): array {
        return $this->activeRequests;
    }

    public function cleanupExpiredRequests(): void {
        foreach ($this->activeRequests as $playerName => $request) {
            if (time() > $request['expiry']) {
                $player = Server::getInstance()->getPlayerExact($playerName);
                if ($player !== null) {
                    $player->kick($this->languageManager->get("2fa-login-expired-kick", ["player_name" => $playerName]));
                }

                $this->bot->editMessageText(
                    $request['chat_id'],
                    $request['message_id'],
                    $this->languageManager->get("2fa-login-expired", ["player_name" => $playerName])
                );
                $this->removeRequest($playerName);
            }
        }
    }

    public function generateUniqueCode(): string {
        return bin2hex(random_bytes(4)); // 8 characters hex code
    }

    public function get2FAStatus(string $playerName): bool {
        return $this->bindingService->isTwoFactorEnabled($playerName);
    }

    public function toggle2FA(string $playerName): bool {
        $currentStatus = $this->bindingService->isTwoFactorEnabled($playerName);
        $newStatus = !$currentStatus;
        $this->bindingService->setTwoFactor($playerName, $newStatus);
        return $newStatus;
    }
}

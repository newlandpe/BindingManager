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

namespace newlandpe\BindingManager\Command;

use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\ServiceContainer;

class TwoFactorCommand implements CommandInterface {

    private BindingService $bindingService;
    private LanguageManager $lang;
    private TelegramBot $bot;

    public function __construct(ServiceContainer $container) {
        $this->bindingService = $container->get(BindingService::class);
        $this->lang = $container->get(LanguageManager::class);
        $this->bot = $container->get(TelegramBot::class);
    }

    public function execute(CommandContext $context): bool {
        $chatId = (int)($context->message['chat']['id'] ?? 0);
        $fromId = (int)($context->message['from']['id'] ?? 0);
        $args = $context->args;

        if ($chatId === 0 || $fromId === 0) {
            return true;
        }

        $subCommand = strtolower($args[0] ?? '');
        $playerName = $args[1] ?? null;

        if ($playerName === null) {
            $this->bot->sendMessage($chatId, $this->lang->get("2fa-usage"));
            return true;
        }

        // Verify that the player is actually bound to this telegram account
        $this->bindingService->getBoundPlayerNames($fromId, function (array $boundNames) use ($chatId, $playerName, $subCommand): void {
            if (!in_array(strtolower($playerName), array_map('strtolower', $boundNames), true)) {
                $this->bot->sendMessage($chatId, $this->lang->get("2fa-not-your-account", ["player_name" => $playerName]));
                return;
            }

            switch ($subCommand) {
                case 'enable':
                    $this->bindingService->setTwoFactor($playerName, true);
                    $this->bot->sendMessage($chatId, $this->lang->get("2fa-enabled", ["player_name" => $playerName]));
                    break;
                case 'disable':
                    $this->bindingService->setTwoFactor($playerName, false);
                    $this->bot->sendMessage($chatId, $this->lang->get("2fa-disabled", ["player_name" => $playerName]));
                    break;
                case 'status':
                    $this->bindingService->isTwoFactorEnabled($playerName, function (bool $enabled) use ($chatId, $playerName): void {
                        $status = $enabled ? "enabled" : "disabled";
                        $message = $this->lang->get("2fa-status-" . $status, ["player_name" => $playerName]);
                        $this->bot->sendMessage($chatId, $message);
                    });
                    break;
                default:
                    $this->bot->sendMessage($chatId, $this->lang->get("2fa-usage"));
                    break;
            }
        });

        return true;
    }
}

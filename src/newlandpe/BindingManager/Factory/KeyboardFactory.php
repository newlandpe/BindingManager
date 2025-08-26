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

namespace newlandpe\BindingManager\Factory;

use newlandpe\BindingManager\LanguageManager;
use newlandpe\BindingManager\Main;

class KeyboardFactory {

    private LanguageManager $lang;

    public function __construct(LanguageManager $lang) {
        $this->lang = $lang;
    }

    /**
     * This is the initial menu for users with no bound accounts.
     * @return array<string, mixed>
     */
    public function createInitialMenu(): array {
        return [
            'inline_keyboard' => [
                [['text' => $this->lang->get('keyboard-main-menu-bind'), 'callback_data' => 'menu:binding']],
                [['text' => $this->lang->get('keyboard-main-menu-help'), 'callback_data' => 'menu:help']]
            ]
        ];
    }

    /**
     * Creates a keyboard with a list of bound player accounts.
     * @param string[] $playerNames
     * @return array<string, mixed>
     */
    public function createAccountListMenu(array $playerNames): array {
        $buttons = [];
        foreach ($playerNames as $playerName) {
            $buttons[] = [['text' => '👤 ' . $playerName, 'callback_data' => 'account:select:' . $playerName]];
        }

        $buttons[] = [['text' => $this->lang->get('keyboard-add-new-account'), 'callback_data' => 'binding:bind']];
        $buttons[] = [['text' => $this->lang->get('keyboard-back-to-main-menu'), 'callback_data' => 'menu:initial']];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Creates a keyboard for managing a specific player account.
     * @param string $playerName
     * @param bool $notificationsEnabled
     * @param bool $twoFaEnabled
     * @return array<string, mixed>
     */
    public function createAccountManagementMenu(string $playerName, bool $notificationsEnabled, bool $twoFaEnabled): array {
        $notificationButtonText = $notificationsEnabled ?
            $this->lang->get('keyboard-account-menu-notifications-enabled') : 
            $this->lang->get('keyboard-account-menu-notifications-disabled');

        $twoFaButtonText = $twoFaEnabled ?
            $this->lang->get('keyboard-account-menu-2fa-disabled') : 
            $this->lang->get('keyboard-account-menu-2fa-enable');

        return [
            'inline_keyboard' => [
                [['text' => $this->lang->get('keyboard-account-menu-info'), 'callback_data' => 'account:info:' . $playerName]],
                [['text' => $notificationButtonText, 'callback_data' => 'account:notifications:' . $playerName]],
                [['text' => $twoFaButtonText, 'callback_data' => 'account:2fa:' . $playerName]],
                [['text' => $this->lang->get('keyboard-account-menu-unbind'), 'callback_data' => 'account:unbind:' . $playerName]],
                [['text' => $this->lang->get('keyboard-account-menu-back'), 'callback_data' => 'menu:binding']],
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createCancelKeyboard(string $code): array {
        if ($this->lang === null) {
            throw new \RuntimeException('LanguageManager not available.');
        }
        return [
            'inline_keyboard' => [
                [['text' => $this->lang->get('keyboard-cancel-binding'), 'callback_data' => 'binding:cancel:' . $code]]
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createUnbindCancelKeyboard(): array {
        if ($this->lang === null) {
            throw new \RuntimeException('LanguageManager not available.');
        }
        return [
            'inline_keyboard' => [
                [['text' => $this->lang->get('keyboard-cancel-unbind'), 'callback_data' => 'unbind:cancel']]
            ]
        ];
    }
}

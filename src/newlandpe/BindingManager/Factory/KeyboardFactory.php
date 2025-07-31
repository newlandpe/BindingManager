<?php

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
     * @return array<string, mixed>
     */
    public function createMainMenu(): array {
        if ($this->lang === null) {
            throw new \RuntimeException('LanguageManager not available.');
        }
        return [
            'inline_keyboard' => [
                [['text' => $this->lang->get('keyboard-main-menu-bind'), 'callback_data' => 'menu:bind']],
                [['text' => $this->lang->get('keyboard-main-menu-help'), 'callback_data' => 'menu:help']]
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createBindingMenu(bool $isBound): array {
        if ($this->lang === null) {
            throw new \RuntimeException('LanguageManager not available.');
        }
        if ($isBound) {
            return [
                'inline_keyboard' => [
                    [['text' => $this->lang->get('keyboard-binding-menu-myinfo'), 'callback_data' => 'binding:myinfo']],
                    [['text' => $this->lang->get('keyboard-binding-menu-notifications'), 'callback_data' => 'binding:notifications']],
                    [['text' => $this->lang->get('keyboard-binding-menu-unbind'), 'callback_data' => 'binding:unbind']]
                ]
            ];
        } else {
            return [
                'inline_keyboard' => [
                    [['text' => $this->lang->get('keyboard-binding-menu-bind'), 'callback_data' => 'binding:bind']]
                ]
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function createCancelKeyboard(): array {
        if ($this->lang === null) {
            throw new \RuntimeException('LanguageManager not available.');
        }
        return [
            'inline_keyboard' => [
                [['text' => $this->lang->get('keyboard-cancel-binding'), 'callback_data' => 'binding:cancel']]
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

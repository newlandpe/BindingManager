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

namespace newlandpe\BindingManager;

use pocketmine\utils\Config;

class LanguageManager
{
    private string $language;
    private Config $messages;

    public function __construct(string $language, string $langFile)
    {
        $this->language = $language;
        $this->messages = new Config($langFile, Config::YAML);
    }

    /**
     * @param array<string, string|int|float> $replacements
     */
    public function get(string $key, array $replacements = []): string
    {
        $message = $this->messages->get($key, "Missing translation for key: $key");
        if (!is_string($message)) {
            $message = "Missing translation for key: $key";
        }
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace("{" . $placeholder . "}", (string)$value, $message);
        }
        return $message;
    }
}
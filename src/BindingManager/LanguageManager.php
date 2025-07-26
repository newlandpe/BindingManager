<?php

declare(strict_types=1);

namespace BindingManager;

use pocketmine\utils\Config;

class LanguageManager {

    private string $language;
    private Config $messages;

    public function __construct(string $language) {
        $this->language = $language;
        $this->loadMessages();
    }

    private function loadMessages(): void {
        $main = Main::getInstance();
        if ($main === null) {
            throw new \RuntimeException('Main instance not available.');
        }
        $langFile = $main->getDataFolder() . "languages/" . $this->language . ".yml";
        if (!file_exists($langFile)) {
            $main->getLogger()->warning("Language '" . $this->language . "' not found. Defaulting to English and creating a new language file.");
            $main->saveResource("languages/" . $this->language . ".yml");
            $this->language = "en";
            $langFile = $main->getDataFolder() . "languages/en.yml";
        }
        $this->messages = new Config($langFile, Config::YAML);
    }

    /**
     * @param array<string, string|int|float> $replacements
     */
    public function get(string $key, array $replacements = []): string {
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

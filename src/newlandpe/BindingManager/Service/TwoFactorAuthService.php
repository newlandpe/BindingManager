<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Service;

use pocketmine\player\Player;

class TwoFactorAuthService {

    /** @var array<string, array{chat_id: int, message_id: int, code: string, expiry: int}> */
    private array $activeRequests = [];

    /** @var array<string, bool> */
    private array $frozenPlayers = [];

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

    public function cleanupExpiredRequests(Main $plugin): void {
        $bot = $plugin->getBot();
        $lang = $plugin->getLanguageManager();

        if ($bot === null || $lang === null) {
            return;
        }

        foreach ($this->activeRequests as $playerName => $request) {
            if (time() > $request['expiry']) {
                $player = $plugin->getServer()->getPlayerExact($playerName);
                if ($player !== null) {
                    $player->kick($lang->get("2fa-login-expired-kick", ["player_name" => $playerName]));
                    $this->unfreezePlayer($player);
                }

                $bot->editMessageText(
                    $request['chat_id'],
                    $request['message_id'],
                    $lang->get("2fa-login-expired", ["player_name" => $playerName])
                );
                $this->removeRequest($playerName);
            }
        }
    }

    public function generateUniqueCode(): string {
        return bin2hex(random_bytes(4)); // 8 characters hex code
    }

    public function freezePlayer(Player $player): void {
        $this->frozenPlayers[strtolower($player->getName())] = true;
    }

    public function unfreezePlayer(Player $player): void {
        unset($this->frozenPlayers[strtolower($player->getName())]);
    }

    public function isPlayerFrozen(Player $player): bool {
        return isset($this->frozenPlayers[strtolower($player->getName())]);
    }
}

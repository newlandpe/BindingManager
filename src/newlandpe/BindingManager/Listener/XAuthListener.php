<?php

declare(strict_types=1);

namespace newlandpe\BindingManager\Listener;

use Luthfi\XAuth\event\PlayerLoginEvent;
use newlandpe\BindingManager\Main;
use newlandpe\BindingManager\Service\TwoFAManager;
use pocketmine\event\Listener;
use pocketmine\player\Player;

class XAuthListener implements Listener {

    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param PlayerLoginEvent $event
     */
    public function onPlayerLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $dataProvider = $this->plugin->getDataProvider();
        if ($dataProvider === null) {
            return;
        }

        $telegramId = $dataProvider->getTelegramIdByPlayerName($player->getName());
        if ($telegramId === null || !$dataProvider->isTwoFactorEnabled($telegramId)) {
            return;
        }

        $twoFactorAuthService = $this->plugin->getTwoFactorAuthService();
        if ($twoFactorAuthService === null) {
            return;
        }

        $event->setAuthenticationDelayed(true);

        $twoFactorAuthService->freezePlayer($player);

        $bot = $this->plugin->getBot();
        if ($bot === null) {
            return;
        }

        $lang = $this->plugin->getLanguageManager();
        if ($lang === null) {
            return;
        }

        $code = $twoFactorAuthService->generateUniqueCode();
        $expiry = time() + (int)$this->plugin->getConfig()->get("2fa_timeout_seconds", 120); // Configurable expiry

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => $lang->get("2fa-keyboard-confirm"), 'callback_data' => '2fa:confirm:' . $player->getName() . ':' . $code],
                    ['text' => $lang->get("2fa-keyboard-deny"), 'callback_data' => '2fa:deny:' . $player->getName() . ':' . $code]
                ]
            ]
        ];

        $messageId = $bot->sendMessage($telegramId, $lang->get("2fa-login-attempt", ["ip" => $player->getNetworkSession()->getIp()]), $keyboard);
        if ($messageId !== null) {
            $twoFactorAuthService->addRequest($player->getName(), $telegramId, $messageId, $code, $expiry);
        }
        $player->sendMessage($lang->get("2fa-prompt"));
    }
}

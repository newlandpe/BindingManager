<?php

declare(strict_types=1);

namespace newlandpe\BindingManager;

use newlandpe\BindingManager\Listener\NotificationListener;
use newlandpe\BindingManager\Provider\DataProviderFactory;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Task\RequestTickTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class Main extends PluginBase {

    private static ?self $instance = null;
    private ?DataProviderInterface $dataProvider = null;
    private ?LanguageManager $languageManager = null;
    private ?TelegramBot $bot = null;
    private int $offset = 0;
    /** @var array<int, string> */
    private array $userStates = [];
    private ?FreezeManager $freezeManager = null;

    public function onEnable(): void {
        self::$instance = $this;
        $this->saveDefaultConfig();

        $this->freezeManager = new FreezeManager();

        $this->getScheduler()->scheduleRepeatingTask(new RequestTickTask(), 1);

        $this->saveResource("languages/en.yml");
        $this->saveResource("languages/uk.yml");

        $language = $this->getConfig()->get("language", "en");
        if (!is_string($language)) {
            $language = "en";
        }
        $this->languageManager = new LanguageManager($language);

        $databaseConfig = $this->getConfig()->get("database", []);
        if (!is_array($databaseConfig)) {
            $databaseConfig = [];
        }
        $codeLengthBytes = $this->getConfig()->get("code_length_bytes", 3); // Default to 3 bytes
        if (!is_int($codeLengthBytes) || $codeLengthBytes <= 0) {
            $this->getLogger()->warning("Invalid 'code_length_bytes' in config.yml. Using default value of 3.");
            $codeLengthBytes = 3;
        }
        $databaseConfig['code_length_bytes'] = $codeLengthBytes;

        $bindingCodeTimeout = $this->getConfig()->get("binding_code_timeout_seconds", 300); // Default to 300 seconds
        if (!is_int($bindingCodeTimeout) || $bindingCodeTimeout <= 0) {
            $this->getLogger()->warning("Invalid 'binding_code_timeout_seconds' in config.yml. Using default value of 300.");
            $bindingCodeTimeout = 300;
        }
        $databaseConfig['binding_code_timeout_seconds'] = $bindingCodeTimeout;

        $this->dataProvider = DataProviderFactory::create($databaseConfig);

        $token = $this->getConfig()->get("telegram_token");
        if (!is_string($token)) {
            $token = "YOUR_TELEGRAM_TOKEN";
        }
        if ($token === "YOUR_TELEGRAM_TOKEN" || $token === '') {
            $this->getLogger()->error("Please set your Telegram token in config.yml");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->bot = new TelegramBot($token, $this->getConfig());
        if (!$this->bot->initialize()) {
            $this->getLogger()->error("Failed to get bot info, disabling plugin.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->getServer()->getPluginManager()->registerEvents(new NotificationListener(), $this);

        $this->startPolling();
    }

    public function startPolling(): void {
        if ($this->bot === null) {
            return;
        }
        $this->bot->getUpdates(function(array $updates) {
            $this->processUpdates($updates);
            // Schedule the next poll only after the current one has completed.
            $this->getScheduler()->scheduleDelayedTask(new class($this) extends Task {
                private Main $main;
                public function __construct(Main $main) { $this->main = $main; }
                public function onRun(): void { $this->main->startPolling(); }
            }, 1);
        });
    }

    /**
     * @param array<array<string, mixed>> $updates
     */
    public function processUpdates(array $updates): void {
        if (count($updates) === 0) {
            return;
        }

        foreach ($updates as $update) {
            if (isset($update['update_id'])) {
                $this->setOffset((int)($update['update_id'] + 1));
                if ($this->bot !== null && $this->languageManager !== null && $this->dataProvider !== null) {
                    $this->bot->processUpdate($update, $this->languageManager, $this->dataProvider);
                }
            }
        }
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function setOffset(int $offset): void {
        $this->getLogger()->debug("Setting new offset: " . $offset);
        $this->offset = $offset;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "confirm") {
            if (!$sender instanceof Player) {
                $langManager = $this->getLanguageManager();
                if (!is_null($langManager)) {
                    $sender->sendMessage($langManager->get("command-only-in-game"));
                }
                return true;
            }
            if (!isset($args[0]) || $args[0] === '') {
                $sender->sendMessage("Usage: /confirm <code>");
                return true;
            }
            $dataProvider = $this->getDataProvider();
            $langManager = $this->getLanguageManager();
            if (!is_null($dataProvider) && !is_null($langManager)) {
                if ($dataProvider->confirmBinding($sender->getName(), $args[0])) {
                    $sender->sendMessage($langManager->get("command-confirm-success"));
                } else {
                    $sender->sendMessage($langManager->get("command-confirm-fail"));
                }
            }
            return true;
        } elseif ($command->getName() === "tg") {
            if (!$sender instanceof Player) {
                $langManager = $this->getLanguageManager();
                if (!is_null($langManager)) {
                    $sender->sendMessage($langManager->get("command-only-in-game"));
                }
                return true;
            }

            if (!isset($args[0])) {
                $langManager = $this->getLanguageManager();
                if (!is_null($langManager)) {
                    $sender->sendMessage($langManager->get("command-usage-tg"));
                }
                return true;
            }

            switch (strtolower($args[0])) {
                case "help":
                    $lang = $this->getLanguageManager();
                    if($lang === null) return true;
                    if($sender->hasPermission("bindingmanager.command.forceunbind")) {
                        $sender->sendMessage($lang->get("command-tg-help-admin"));
                    } else {
                        $sender->sendMessage($lang->get("command-tg-help-player"));
                    }
                    return true;
                case "unbind":
                    if (!isset($args[1]) || strtolower($args[1]) !== "confirm") {
                        $langManager = $this->getLanguageManager();
                        if (!is_null($langManager)) {
                            $sender->sendMessage($langManager->get("command-usage-tg-unbind"));
                        }
                        return true;
                    }
                    if (!isset($args[2]) || $args[2] === '') {
                        $langManager = $this->getLanguageManager();
                        if (!is_null($langManager)) {
                            $sender->sendMessage($langManager->get("command-usage-tg-unbind"));
                        }
                        return true;
                    }
                    $code = $args[2];
                    $dataProvider = $this->getDataProvider();
                    $langManager = $this->getLanguageManager();
                    if (!is_null($dataProvider) && !is_null($langManager)) {
                        if ($dataProvider->confirmUnbinding($sender->getName(), $code)) {
                            $sender->sendMessage($langManager->get("command-unbind-confirm-success"));
                        } else {
                            $sender->sendMessage($langManager->get("command-unbind-confirm-fail"));
                        }
                    }
                    return true;
                case "reset":
                    $dataProvider = $this->getDataProvider();
                    $langManager = $this->getLanguageManager();
                    $bot = $this->getBot();

                    if (is_null($dataProvider) || is_null($langManager) || is_null($bot)) {
                        return true;
                    }

                    $code = $dataProvider->initiateInGameReset($sender->getName());

                    if ($code === null) {
                        $sender->sendMessage($langManager->get("command-ingame-reset-fail"));
                        return true;
                    }

                    $telegramId = $dataProvider->getTelegramIdByPlayerName($sender->getName());
                    if ($telegramId !== null) {
                        $bot->sendMessage($telegramId, $langManager->get("telegram-ingame-reset-alert"));
                    }

                    $sender->sendMessage($langManager->get("command-ingame-reset-initiated", ["code" => $code]));
                    $this->getLogger()->info("Player " . $sender->getName() . " has initiated an in-game account reset. Their code is " . $code);

                    return true;
                case "confirmreset":
                    if (!$sender->hasPermission("bindingmanager.command.forceunbind")) {
                        $langManager = $this->getLanguageManager();
                        if (!is_null($langManager)) {
                            $sender->sendMessage($langManager->get("command-no-permission"));
                        }
                        return true;
                    }
                    if (!isset($args[1]) || !isset($args[2])) {
                        $langManager = $this->getLanguageManager();
                        if (!is_null($langManager)) {
                            $sender->sendMessage($langManager->get("command-usage-tg-confirmreset"));
                        }
                        return true;
                    }
                    $playerName = $args[1];
                    $code = $args[2];
                    $dataProvider = $this->getDataProvider();
                    $langManager = $this->getLanguageManager();

                    if (!is_null($dataProvider) && !is_null($langManager)) {
                        if ($dataProvider->confirmInGameReset($playerName, $code)) {
                            $sender->sendMessage($langManager->get("command-confirmreset-success", ["player_name" => $playerName]));
                        } else {
                            $sender->sendMessage($langManager->get("command-confirmreset-fail", ["player_name" => $playerName]));
                        }
                    }
                    return true;
                case "forceunbind":
                    if (!$sender->hasPermission("bindingmanager.command.forceunbind")) {
                        $langManager = $this->getLanguageManager();
                        if (!is_null($langManager)) {
                            $sender->sendMessage($langManager->get("command-no-permission"));
                        }
                        return true;
                    }
                    if (!isset($args[1]) || $args[1] === '') {
                        $langManager = $this->getLanguageManager();
                        if (!is_null($langManager)) {
                            $sender->sendMessage($langManager->get("command-usage-tg-forceunbind"));
                        }
                        return true;
                    }
                    $playerName = $args[1];
                    $dataProvider = $this->getDataProvider();
                    $langManager = $this->getLanguageManager();
                    if (!is_null($dataProvider) && !is_null($langManager)) {
                        $telegramId = $dataProvider->getTelegramIdByPlayerName($playerName);
                        if (!is_null($telegramId)) {
                            if ($dataProvider->unbindByTelegramId($telegramId)) {
                                $sender->sendMessage($langManager->get("command-forceunbind-success", ["player_name" => $playerName]));
                            } else {
                                $sender->sendMessage($langManager->get("command-forceunbind-fail", ["player_name" => $playerName]));
                            }
                        } else {
                            $sender->sendMessage($langManager->get("command-forceunbind-player-not-bound", ["player_name" => $playerName]));
                        }
                    }
                    return true;
                default:
                    $langManager = $this->getLanguageManager();
                    if (!is_null($langManager)) {
                        $sender->sendMessage($langManager->get("command-unknown-subcommand"));
                    }
                    return true;
            }
        }
        return false;
    }

    public static function getInstance(): ?self {
        return self::$instance;
    }

    public function getLanguageManager(): ?LanguageManager {
        return $this->languageManager;
    }

    public function getDataProvider(): ?DataProviderInterface {
        return $this->dataProvider;
    }

    public function getBot(): ?TelegramBot {
        return $this->bot;
    }

    public function getFreezeManager(): FreezeManager {
        return $this->freezeManager;
    }

    public function getUserState(int $userId): ?string {
        return $this->userStates[$userId] ?? null;
    }

    public function setUserState(int $userId, ?string $state): void {
        if ($state === null) {
            unset($this->userStates[$userId]);
        } else {
            $this->userStates[$userId] = $state;
        }
    }
}

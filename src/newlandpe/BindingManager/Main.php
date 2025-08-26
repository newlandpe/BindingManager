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

use newlandpe\BindingManager\Command\AdminPlayerInfoCommand;
use newlandpe\BindingManager\Command\BindingCommand;
use newlandpe\BindingManager\Command\CommandInterface;
use newlandpe\BindingManager\Command\Game\ConfirmCommand;
use newlandpe\BindingManager\Command\Game\TgCommand;
use newlandpe\BindingManager\Command\HelpCommand;
use newlandpe\BindingManager\Command\MyInfoCommand;
use newlandpe\BindingManager\Command\ResetBindingCommand;
use newlandpe\BindingManager\Command\StartCommand;
use newlandpe\BindingManager\Command\TwoFactorCommand;
use newlandpe\BindingManager\Command\UnbindCommand;
use newlandpe\BindingManager\Handler\CallbackQueryHandler;
use newlandpe\BindingManager\Handler\CommandHandler;
use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\Listener\NotificationListener;
use newlandpe\BindingManager\Listener\XAuthListener;
use newlandpe\BindingManager\Provider\DataProviderFactory;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use newlandpe\BindingManager\Service\BindingService;
use newlandpe\BindingManager\Service\PersistentOffsetManager;
use newlandpe\BindingManager\Service\PersistentUserStateManager;
use newlandpe\BindingManager\Service\OffsetManager;
use newlandpe\BindingManager\Service\TwoFactorAuthService;
use newlandpe\BindingManager\Service\UserStateManager;
use newlandpe\BindingManager\Steps\BindingManager2FAStep;
use newlandpe\BindingManager\Task\BindingCleanupTask;
use newlandpe\BindingManager\Task\RequestTickTask;
use newlandpe\BindingManager\Task\TwoFACleanupTask;
use newlandpe\BindingManager\Telegram\TelegramBot;
use newlandpe\BindingManager\Util\CodeGenerator;
use newlandpe\BindingManager\Util\ServiceContainer;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

class Main extends PluginBase implements Listener {

    private ServiceContainer $container;

    public function onEnable(): void {
        $this->saveDefaultConfig();

        // Ensure language directory exists and save default languages
        $this->saveResource("languages/en.yml");
        $this->saveResource("languages/uk.yml");

        $this->container = new ServiceContainer();
        $this->registerServices();

        $this->getScheduler()->scheduleRepeatingTask(new RequestTickTask(), 1);
        $this->getScheduler()->scheduleRepeatingTask(new TwoFACleanupTask($this->container->get(TwoFactorAuthService::class)), 20);
        $this->getScheduler()->scheduleRepeatingTask(new BindingCleanupTask($this->container->get(BindingService::class)), 20 * 60 * 60);

        $this->getServer()->getPluginManager()->registerEvents($this->container->get(NotificationListener::class), $this);
        $this->getServer()->getPluginManager()->registerEvents($this->container->get(XAuthListener::class), $this);

        $this->registerXAuthStep();
        $this->registerInGameCommands();

        $this->container->get(TelegramBot::class)->startPolling();
    }

    private function registerServices(): void {
        // Core Plugin & Server Objects
        $this->container->set(Main::class, $this);
        $this->container->set(\pocketmine\Server::class, $this->getServer());
        $this->container->set(\pocketmine\utils\Config::class, $this->getConfig());

        // Utility Services
        $this->container->register(LanguageManager::class, function() {
            $lang = $this->getConfig()->get('language', 'en');
            $langFile = $this->getDataFolder() . "languages/" . $lang . ".yml";
            if (!file_exists($langFile)) {
                $this->getLogger()->warning("Language '" . $lang . "' not found, defaulting to English.");
                $lang = 'en';
                $langFile = $this->getDataFolder() . "languages/en.yml";
            }
            return new LanguageManager($lang, $langFile);
        });
        $this->container->register(CodeGenerator::class, fn() => new CodeGenerator((int)$this->getConfig()->get('code-length-bytes', 3)));

        // DataProvider Service
        $this->container->register(DataProviderInterface::class, fn() => DataProviderFactory::create($this->getConfig()->get('database', []), $this->getDataFolder()));

        // State and Offset Managers
        $this->container->register(OffsetManager::class, fn(ServiceContainer $c) => new PersistentOffsetManager($c->get(DataProviderInterface::class)));
        $this->container->register(UserStateManager::class, fn(ServiceContainer $c) => new PersistentUserStateManager($c->get(DataProviderInterface::class)));

        // Core Services
        $this->container->register(BindingService::class, fn(ServiceContainer $c) => new BindingService(
            $c->get(DataProviderInterface::class),
            $c->get(CodeGenerator::class),
            $c->get(\pocketmine\utils\Config::class),
            $c->get(Main::class)
        ));
        $this->container->register(TwoFactorAuthService::class, fn(ServiceContainer $c) => new TwoFactorAuthService(
            $c->get(TelegramBot::class),
            $c->get(LanguageManager::class),
            $c->get(BindingService::class),
            $c->get(\pocketmine\utils\Config::class),
            $c->get(Main::class)
        ));

        // Keyboard & Command Handlers
        $this->container->register(KeyboardFactory::class, fn(ServiceContainer $c) => new KeyboardFactory($c->get(LanguageManager::class)));
        
        $this->container->register(CommandHandler::class, function(ServiceContainer $c) {
            $bot = $c->get(TelegramBot::class); // This will be the instance from the container
            $keyboardFactory = $c->get(KeyboardFactory::class);
            $commands = $this->createTelegramCommands($c);
            return new CommandHandler($bot, $keyboardFactory, $commands);
        });

        // Telegram Bot and Handlers
        $this->container->register(TelegramBot::class, function (ServiceContainer $c) {
            $token = $this->getConfig()->get("telegram-token");
            if (!is_string($token) || $token === 'YOUR_TELEGRAM_TOKEN' || $token === '') {
                $this->getLogger()->error("Please set your Telegram token in config.yml");
                $this->getServer()->getPluginManager()->disablePlugin($this);
                throw new \RuntimeException("Telegram token not configured.");
            }

            // 1. Create bot without command handler
            $bot = new TelegramBot(
                $token, 
                $this->getConfig(), 
                $c->get(KeyboardFactory::class),
                $c->get(OffsetManager::class),
                $c->get(UserStateManager::class)
            );

            return $bot;
        });

        $this->container->register(CallbackQueryHandler::class, fn(ServiceContainer $c) => new CallbackQueryHandler($c));

        // Listeners
        $this->container->register(NotificationListener::class, fn(ServiceContainer $c) => new NotificationListener($c));
        $this->container->register(XAuthListener::class, fn(ServiceContainer $c) => new XAuthListener($c));

        // Set CommandHandler on TelegramBot after both are registered
        $telegramBot = $this->container->get(TelegramBot::class);
        $telegramBot->setCommandHandler($this->container->get(CommandHandler::class));
        $telegramBot->setCallbackQueryHandler($this->container->get(CallbackQueryHandler::class));

        if (!$telegramBot->initialize()) {
            $this->getLogger()->error("Failed to get bot info, disabling plugin.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            throw new \RuntimeException("Failed to initialize Telegram bot.");
        }
    }

    private function registerInGameCommands(): void {
        $this->getServer()->getCommandMap()->register("bindingmanager", new ConfirmCommand($this->container));
        $this->getServer()->getCommandMap()->register("bindingmanager", new TgCommand($this->container));
    }

    /**
     * @return array<string, \newlandpe\BindingManager\Command\CommandInterface>
     */
    private function createTelegramCommands(ServiceContainer $container): array {
        // All commands now receive the container and fetch their own dependencies.
        return [
            'start' => new StartCommand($container),
            'binding' => new BindingCommand($container),
            'help' => new HelpCommand($container),
            'myinfo' => new MyInfoCommand($container),
            'unbind' => new UnbindCommand($container),
            'adminplayerinfo' => new AdminPlayerInfoCommand($container),
            'resetbinding' => new ResetBindingCommand($container),
            '2fa' => new TwoFactorCommand($container),
        ];
    }

    private function registerXAuthStep(): void {
        $xAuthPlugin = $this->getServer()->getPluginManager()->getPlugin("XAuth");
        if ($xAuthPlugin instanceof \Luthfi\XAuth\Main) {
            $xAuthPlugin->registerAuthenticationStep(new BindingManager2FAStep($this));
        } else {
            $this->getLogger()->warning("XAuth plugin not found. 2FA will not be integrated with XAuth's authentication flow.");
        }
    }

    public function getContainer(): ServiceContainer {
        return $this->container;
    }
}

# BindingManager

A PocketMine-MP plugin to bind player accounts to Telegram for authentication, notifications, and more. This plugin provides a flexible API for other plugins to extend its functionality.

## Features

- **Secure Binding:** Bind your in-game account to your Telegram account via a private chat with the bot.
- **Localization:** Multi-language support. English and Ukrainian are included by default.
- **Extensible API:** Provides an event-based system for other plugins to add custom data to player information displays.

## Commands

Here are the commands available in BindingManager:

- `/confirm <code>`: Confirms your Telegram account binding with the provided code.
- `/tg <subcommand>`: Manages your Telegram bindings.
  - `/tg unbind confirm <code>`: Initiates and confirms the unbinding process.

## API for Developers

BindingManager makes it easy to integrate with your own plugins. You can check if a player is bound or even add your own custom data to the `/myinfo` command.

### Checking if a player is bound

```php
$bindingManager = $this->getServer()->getPluginManager()->getPlugin("BindingManager");
if ($bindingManager instanceof \BindingManager\Main) {
    $isBound = $bindingManager->getDataProvider()->isPlayerBound($player->getName());
    if ($isBound) {
        // Player is bound, do something...
    }
}
```

### Adding custom data to /myinfo

To add your own data to the `/myinfo` command, you need to listen for the `PlayerDataInfoEvent`. The command uses a template system, so you can add your own placeholders.

**1. Add your placeholder to the language files:**

Open `resources/languages/en.yml` and add your placeholder to the templates:

```yaml
telegram-myinfo-online: |
  Nickname: {nickname}
  Status: Online
  Health: {health}
  Position: {position}
  Money: {money}
```

**2. Create a listener class:**

```php
// In YourPlugin/src/YourNamespace/InfoListener.php

namespace YourNamespace;

use pocketmine\event\Listener;
use BindingManager\Event\PlayerDataInfoEvent;

class InfoListener implements Listener {

    /**
     * @param PlayerDataInfoEvent $event
     * @priority NORMAL
     * @ignoreCancelled false
     */
    public function onPlayerDataInfo(PlayerDataInfoEvent $event): void {
        $player = $event->getPlayer();

        // Example: Get player's money from an economy plugin
        // $money = $this->economyProvider->getMoney($player);
        $money = 1000; // Dummy value

        $event->addPlaceholder("money", (string)$money);
    }
}
```

**3. Register the listener in your main plugin file:**

```php
// In YourPlugin/src/YourNamespace/Main.php

$this->getServer()->getPluginManager()->registerEvents(new InfoListener(), $this);
```

**Placeholder Collision Rule:** If multiple plugins try to add a placeholder with the same key, only the first one will be registered. This is a "first come, first served" rule.

### Sending Notifications

You can easily send notifications to a player's Telegram account from your own plugin by calling the `SendNotificationEvent`.

```php
use BindingManager\Event\SendNotificationEvent;
use pocketmine\player\Player;

// Get the player object
$player = $this->getServer()->getPlayerExact("SomePlayer");

if ($player instanceof Player) {
    $message = "Hello from my plugin! You have received a new item.";
    $event = new SendNotificationEvent($player, $message);
    $event->call();
}
```

The notification will only be sent if the player has bound their account and has notifications enabled.

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2 (CSSM-ULv2). Please note that this is a custom license. See the [LICENSE](LICENSE) file for details.

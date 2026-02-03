# BindingManager

[![Poggit CI](https://poggit.pmmp.io/ci.shield/newlandpe/BindingManager/BindingManager)](https://poggit.pmmp.io/ci/newlandpe/BindingManager/BindingManager)

A PocketMine-MP plugin to bind player accounts to Telegram for authentication, notifications, and more. This plugin provides a flexible API for other plugins to extend its functionality.

## Features

- **Secure Binding:** Bind your in-game account to your Telegram account via a private chat with the bot.
- **Localization:** Multi-language support. English and Ukrainian are included by default.
- **Extensible API:** Provides an event-based system for other plugins to add custom data to player information displays.

## Configuration

Setting up BindingManager requires a few steps:

1. **Get a Telegram Bot Token:**
   - Talk to [@BotFather](https://t.me/BotFather) on Telegram.
   - Create a new bot by sending the `/newbot` command.
   - BotFather will give you a unique token. Copy this token.

2. **Edit `config.yml`:**
   - Open the `plugin_data/BindingManager/config.yml` file.
   - Paste your bot token into the `telegram-token` field.

3. **Add Your Admin ID:**
   - You need to add your personal Telegram User ID to the `admins:` list in the config. This gives you administrative rights within the bot.
   - **To get your Telegram User ID:**
     1. Open the Telegram app.
     2. Find and start a chat with the bot: [@my_id_bot](https://t.me/my_id_bot).
     3. The bot will immediately reply with your numeric User ID.
     4. Copy this ID and add it to the `admins:` list in `config.yml`.

### Two-Factor Authentication (2FA) Configuration

BindingManager integrates with XAuth's authentication flow to provide 2FA. The behavior of when 2FA is triggered (e.g., always, or only after password login) is controlled by the order of authentication steps in **XAuth's `config.yml`**.

In addition to configuring XAuth's authentication flow, you must also set the `two-factor-mode` in **BindingManager's `config.yml`** to control when 2FA is initiated.

- **`two-factor-mode: 'always'`**: 2FA will be triggered for every login attempt, regardless of whether it's a manual login or auto-login. This corresponds to placing `binding_manager_2fa` at the beginning of XAuth's `authentication-flow-order`.
- **`two-factor-mode: 'after_password'`**: 2FA will only be triggered after a successful password login (or auto-login). This corresponds to placing `binding_manager_2fa` after `xauth_login` (and optionally `auto_login`) in XAuth's `authentication-flow-order`. This mode ensures that players first authenticate with their password before being prompted for 2FA.

Example of `BindingManager`'s `config.yml` for "after_password" mode:
```yaml
# ... other configurations ...
two-factor-mode: 'after_password'
```

To configure 2FA behavior:

1. **Locate XAuth's `config.yml`:** This file is typically found in `plugin_data/XAuth/config.yml`.
2. **Find the `authentication-flow-order` section:** This section defines the sequence of authentication steps.
3. **Adjust the order of `binding_manager_2fa`:**
   - **"Always" trigger 2FA (before password login/auto-login):**
     Place `binding_manager_2fa` at the beginning of the `authentication-flow-order` list.
     Example:
     ```yaml
     authentication-flow-order:
       - "binding_manager_2fa"
       - "auto_login"
       - "xauth_login"
       - "xauth_register"
     ```
   - **Trigger 2FA "after password" login (or auto-login):**
     Place `binding_manager_2fa` after `xauth_login` (and optionally `auto_login`) in the `authentication-flow-order` list.
     Example:
     ```yaml
     authentication-flow-order:
       - "auto_login"
       - "xauth_login"
       - "binding_manager_2fa"
       - "xauth_register"
     ```
     *Note: If a player is authenticated via `auto_login`, the `xauth_login` step is skipped, and `binding_manager_2fa` will be triggered immediately after `auto_login` completes.*

## How to Use (For Players)

1. **Start a chat with the bot:** Open your Telegram and start a conversation with the bot you just configured.
1. **Get your binding code:** In the bot's chat, use the `/start` command. The bot will give you a unique code.
1. **Bind your account in-game:** Log in to the Minecraft server and use the command `/confirm <code>`, replacing `<code>` with the code you received from the bot.

## Commands

BindingManager registers the following commands for use in-game:

- `/confirm <code>`: Confirms a player's Telegram account binding with the code they received from the bot.
- `/tg <subcommand>`: Allows players to manage their Telegram bindings.
  - `/tg unbind confirm <code>`: Initiates and confirms the unbinding process.

### Telegram Bot Commands

For direct interaction with accounts via Telegram, the bot understands these commands:

**For Players:**
- `/start`: Initiates interaction with the bot, often used to begin the account binding process.
- `/binding <nickname>`: Allows a player to bind their in-game account to the Telegram chat.
- `/myinfo <nickname>`: Displays information about a player's bound account.
- `/unbind <nickname>`: Initiates the unbinding process for a specific player account.
- `/2fa <enable|disable|status> <nickname>`: Manages two-factor authentication settings for a player's account.
- `/help`: Provides a list of available bot commands.

**For Admins:**
- `/adminplayerinfo <nickname>`: Looks up the binding status and information for a specific player.
- `/resetbinding <nickname>`: Forcibly removes the Telegram binding from a player's account.

## API for Developers

BindingManager makes it easy to integrate with your own plugins. You can check if a player is bound or even add your own custom data to the `/myinfo` command.

### Checking if a player is bound

```php
$bindingManager = $this->getServer()->getPluginManager()->getPlugin("BindingManager");
if ($bindingManager instanceof \newlandpe\BindingManager\Main) {
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
telegram-myinfo-online: |-
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

use newlandpe\BindingManager\Event\PlayerDataInfoEvent;
use pocketmine\event\Listener;

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
use newlandpe\BindingManager\Event\SendNotificationEvent;
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

### Listening for Account Binding

You can listen for the `AccountBoundEvent` to perform actions when a player successfully binds their account.

```php
use newlandpe\BindingManager\Event\AccountBoundEvent;
use pocketmine\event\Listener;

class MyListener implements Listener {

    /**
     * @param AccountBoundEvent $event
     * @priority NORMAL
     * @ignoreCancelled false // The event is cancellable
     */
    public function onAccountBound(AccountBoundEvent $event): void {
        $player = $event->getPlayer();
        $telegramId = $event->getTelegramId();

        // Example: Give the player a reward for binding their account
        // $this->giveReward($player);
        $player->sendMessage("Thanks for binding! You've received a special reward.");

        // You can also cancel the event to prevent the binding from completing
        // if certain conditions are not met.
        // $event->cancel();
    }
}
```

### Listening for Account Unbinding

Similarly, you can listen for the `AccountUnboundEvent` to react when a player unbinds their account.

```php
use newlandpe\BindingManager\Event\AccountUnboundEvent;
use pocketmine\event\Listener;

class MyListener implements Listener {

    /**
     * @param AccountUnboundEvent $event
     * @priority NORMAL
     */
    public function onAccountUnbound(AccountUnboundEvent $event): void {
        $player = $event->getPlayer(); // This is an IPlayer, so the player might be offline
        $telegramId = $event->getTelegramId();

        // Example: Log the unbinding event
        $this->getServer()->getLogger()->info("Player " . $player->getName() . " (Telegram ID: " . $telegramId . ") has unbound their account.");
    }
}
```

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2.0 (CSSM-ULv2). Please note that this is a custom license. See the [LICENSE](LICENSE) file for details.

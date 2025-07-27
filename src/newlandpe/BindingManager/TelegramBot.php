<?php

declare(strict_types=1);

namespace newlandpe\BindingManager;

use newlandpe\BindingManager\Command\CommandContext;
use newlandpe\BindingManager\Factory\KeyboardFactory;
use newlandpe\BindingManager\Handler\CallbackQueryHandler;
use newlandpe\BindingManager\Handler\CommandHandler;
use newlandpe\BindingManager\Provider\DataProviderInterface;
use pocketmine\Server;
use pocketmine\utils\Config;

class TelegramBot {

    private string $token;
    private string $username = '';
    private int $id = 0;
    private CommandHandler $commandHandler;
    private CallbackQueryHandler $callbackQueryHandler;
    private KeyboardFactory $keyboardFactory;
    private Config $config;

    public function __construct(string $token, Config $config) {
        $this->token = $token;
        $this->config = $config;
        $this->keyboardFactory = new KeyboardFactory();
        $this->commandHandler = new CommandHandler($this, $this->keyboardFactory);
        $this->callbackQueryHandler = new CallbackQueryHandler($this, $this->keyboardFactory);
    }

    public function initialize(): bool {
        $url = 'https://api.telegram.org/bot' . $this->token . '/getMe';
        $ch = curl_init();
        if ($ch === false) {
            return false;
        }

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ];

        $forceResolveHosts = $this->config->getNested("network.force-resolve-hosts", []);
        if (is_array($forceResolveHosts) && count($forceResolveHosts) > 0) {
            $resolveArray = [];
            foreach ($forceResolveHosts as $host => $ip) {
                $resolveArray[] = "{$host}:443:{$ip}";
            }
            $curlOptions[CURLOPT_RESOLVE] = $resolveArray;
        }

        $customCaBundle = $this->config->getNested("network.custom-ca-bundle", "");
        if (is_string($customCaBundle) && $customCaBundle !== '') {
            $curlOptions[CURLOPT_CAINFO] = $customCaBundle;
        }

        curl_setopt_array($ch, $curlOptions);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return false;
        }

        if (!is_string($response)) {
            return false;
        }

        $data = json_decode($response, true);
        if ($data === null) {
            return false;
        }
        if (is_array($data) && ($data['ok'] ?? false) === true && isset($data['result']['username'], $data['result']['id'])) {
            $this->username = $data['result']['username'];
            $this->id = (int)($data['result']['id'] ?? 0);
            return true;
        }

        return false;
    }

    public function getUsername(): string {
        return $this->username;
    }

    public function getId(): int {
        return $this->id;
    }

    public function getCommandHandler(): CommandHandler
    {
        return $this->commandHandler;
    }

    /**
     * @param array<string, mixed> $update
     */
    public function processUpdate(array $update, LanguageManager $lang, DataProviderInterface $dataProvider): void {
        $logger = Server::getInstance()->getLogger();
        // $logger->info("[BindingManager] Processing update ID: " . ($update['update_id'] ?? 'N/A'));

        $main = Main::getInstance();
        if ($main === null) return;

        $chatId = 0;
        $keyboardFactory = $this->keyboardFactory;

        if (isset($update['message']) && is_array($update['message'])) {
            $fromId = 0;
            if (isset($update['message']['from']) && is_array($update['message']['from'])) {
                $fromId = (int)($update['message']['from']['id'] ?? 0);
            }
            $text = $update['message']['text'] ?? null;

            if ($fromId !== 0 && $text !== null) {
                $state = $main->getUserState($fromId);
                if ($state === 'awaiting_nickname') {
                    if (strtolower($text) === '/cancel') {
                        $main->setUserState($fromId, null); // Reset state
                        $this->sendMessage($chatId, $lang->get("telegram-binding-cancelled"));
                        return; // Exit after handling cancel
                    }
                    $main->setUserState($fromId, null); // Reset state
                    $command = $this->commandHandler->findCommand('binding');
                    if ($command !== null) {
                        $keyboardFactory = new KeyboardFactory();
                        $context = new CommandContext($this, $lang, $dataProvider, $keyboardFactory, $update['message'], [$text]);
                        $command->execute($context);
                    }
                    return;
                } elseif ($state === 'awaiting_unbind_confirm') {
                    if (strtolower($text) === '/cancel') {
                        $main->setUserState($fromId, null); // Reset state
                        $this->sendMessage($chatId, $lang->get("telegram-unbind-cancelled"));
                        return;
                    }
                }
            }

            $this->commandHandler->handle($update['message'], $lang, $dataProvider);
        } elseif (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->callbackQueryHandler->handle($update['callback_query'], $lang, $dataProvider);
        }
    }

    /**
     * @param int $chatId
     * @param string $text
     * @param array<string, mixed>|null $replyMarkup
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        $this->request('sendMessage', $params);
    }

    /**
     * @param int $chatId
     * @param int $messageId
     * @param string $text
     * @param array<string, mixed>|null $replyMarkup
     */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }
        $this->request('editMessageText', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function request(string $method, array $params = [], ?callable $callback = null): void {
        $url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
        $ch = curl_init();
        if ($ch === false) {
            Server::getInstance()->getLogger()->error("[BindingManager] Failed to initialize cURL");
            if ($callback !== null) {
                $callback(null);
            }
            return;
        }

        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10,
        ];

        $forceResolveHosts = $this->config->getNested("network.force-resolve-hosts", []);
        if (is_array($forceResolveHosts) && count($forceResolveHosts) > 0) {
            $resolveArray = [];
            foreach ($forceResolveHosts as $host => $ip) {
                $resolveArray[] = "{$host}:443:{$ip}";
            }
            $curlOptions[CURLOPT_RESOLVE] = $resolveArray;
        }

        $customCaBundle = $this->config->getNested("network.custom-ca-bundle", "");
        if (is_string($customCaBundle) && $customCaBundle !== '') {
            $curlOptions[CURLOPT_CAINFO] = $customCaBundle;
        }

        curl_setopt_array($ch, $curlOptions);
        AsyncRequestManager::getInstance()->addRequest($ch, $callback);
    }

    public function getUpdates(callable $callback): void {
        $main = Main::getInstance();
        if ($main === null) {
            $callback([]);
            return;
        }
        $params = [
            'offset' => $main->getOffset(),
            'timeout' => 30, // Use long-polling
            'allowed_updates' => json_encode(['message', 'callback_query'])
        ];
        $this->request('getUpdates', $params, function($response) use ($callback) {
            if ($response === null) {
                // Request might have timed out, which is normal for long-polling.
                $callback([]);
                return;
            }
            if (!is_string($response)) {
                Server::getInstance()->getLogger()->error("[BindingManager] getUpdates received non-string response: " . gettype($response));
                $callback([]);
                return;
            }
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Server::getInstance()->getLogger()->error("[BindingManager] getUpdates JSON decode error: " . json_last_error_msg() . ". Response: " . $response);
                $callback([]);
                return;
            }
            if (($data['ok'] ?? false) === true && isset($data['result'])) {
                $callback($data['result']);
            } else {
                // Handle potential errors from Telegram API
                if(isset($data['description'])){
                    Server::getInstance()->getLogger()->error("[BindingManager] getUpdates error: " . $data['description']);
                }
                $callback([]);
            }
        });
    }
}

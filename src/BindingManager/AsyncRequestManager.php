<?php

declare(strict_types=1);

namespace BindingManager;

use pocketmine\Server;

class AsyncRequestManager {

    private static ?self $instance = null;
    private \CurlMultiHandle $multi_handle;
    /**
     * @var array<int, array{handle: \CurlHandle, callback: ?callable}>
     */
    private array $requests = [];

    private function __construct() {
        $this->multi_handle = curl_multi_init();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addRequest(\CurlHandle $ch, ?callable $callback = null): void {
        curl_multi_add_handle($this->multi_handle, $ch);
        $this->requests[(int)$ch] = ['handle' => $ch, 'callback' => $callback];
    }

    public function tick(): void {
        if (count($this->requests) === 0) {
            return;
        }

        $active = 0;
        do {
            $status = curl_multi_exec($this->multi_handle, $active);
        } while ($status === CURLM_CALL_MULTI_PERFORM);

        while ($done = curl_multi_info_read($this->multi_handle)) {
            if (!is_array($done)) {
                continue;
            }
            $ch = $done['handle'];
            if (!$ch instanceof \CurlHandle) {
                continue;
            }
            $requestInfo = $this->requests[(int)$ch] ?? null;
            if (!is_array($requestInfo)) {
                continue;
            }

            $response = curl_multi_getcontent($ch);
            if ($requestInfo['callback'] !== null) {
                ($requestInfo['callback'])($response);
            }

            curl_multi_remove_handle($this->multi_handle, $ch);
            curl_close($ch);
            unset($this->requests[(int)$ch]);
        }
    }
}

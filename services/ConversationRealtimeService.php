<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\user\models\User;
use Yii;

/**
 * Bridges Conversations to the optional, local WebSocket relay.
 *
 * No message text crosses this boundary. A signed browser ticket grants one
 * person access to one already-authorized conversation for five minutes.
 */
final class ConversationRealtimeService
{
    private const TICKET_LIFETIME = 300;

    /** @return array{url:string,token:string}|null */
    public function socketConnection(Conversation $conversation, User $user): ?array
    {
        $config = $this->config();
        if ($config === null || !$conversation->content->canView()) {
            return null;
        }

        $payload = $this->base64UrlEncode((string) json_encode([
            'v' => 1,
            'u' => (int) $user->id,
            'c' => (int) $conversation->id,
            'e' => time() + self::TICKET_LIFETIME,
        ], JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $payload, $config['sharedSecret']);

        // The ticket is sent as a WebSocket subprotocol header, not as a URL
        // parameter, so it cannot end up in ordinary proxy access logs.
        return ['url' => rtrim($config['publicUrl'], '/'), 'token' => $payload . '.' . $signature];
    }

    public function publishMessage(Conversation $conversation, ConversationMessage $message): void
    {
        $config = $this->config();
        if ($config === null) {
            return;
        }

        $payload = (string) json_encode([
            'event' => 'conversation.message.created',
            'conversationId' => (int) $conversation->id,
            'messageId' => (int) $message->id,
            'timestamp' => time(),
        ], JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $payload, $config['sharedSecret']);

        // A relay outage must never turn a successfully stored chat message
        // into an error. Browsers keep their regular polling fallback.
        try {
            if (function_exists('curl_init')) {
                $handle = curl_init($config['publishUrl']);
                curl_setopt_array($handle, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Conversations-Signature: ' . $signature],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT_MS => 250,
                    CURLOPT_TIMEOUT_MS => 750,
                ]);
                curl_exec($handle);
                curl_close($handle);
                return;
            }

            file_get_contents($config['publishUrl'], false, stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nX-Conversations-Signature: {$signature}\r\n",
                'content' => $payload,
                'timeout' => 1,
                'ignore_errors' => true,
            ]]));
        } catch (\Throwable $error) {
            Yii::warning('Conversations real-time relay is temporarily unavailable: ' . $error->getMessage(), __METHOD__);
        }
    }

    /** @return array{publicUrl:string,publishUrl:string,sharedSecret:string}|null */
    private function config(): ?array
    {
        // Keep the production secret in HumHub's protected application
        // configuration, outside the module directory. The GitHub Module
        // Manager replaces that directory during an update. A module-local
        // file remains a convenient, ignored fallback for local development.
        $externalConfigFile = Yii::getAlias('@app/config/conversations-realtime.php');
        $configFile = is_file($externalConfigFile)
            ? $externalConfigFile
            : dirname(__DIR__) . '/config/realtime.local.php';
        $config = is_file($configFile) ? require $configFile : [];
        if (!is_array($config)) {
            $config = [];
        }
        if (($config['enabled'] ?? false) !== true) {
            return null;
        }

        $publicUrl = rtrim((string) ($config['publicUrl'] ?? ''), '/');
        $publishUrl = (string) ($config['publishUrl'] ?? '');
        $sharedSecret = (string) ($config['sharedSecret'] ?? '');
        $publicScheme = parse_url($publicUrl, PHP_URL_SCHEME);
        if (!in_array($publicScheme, ['ws', 'wss'], true) || !$this->isLocalPublishUrl($publishUrl) || strlen($sharedSecret) < 32) {
            Yii::warning('Conversations real-time configuration is incomplete or unsafe; polling remains active.', __METHOD__);
            return null;
        }

        return compact('publicUrl', 'publishUrl', 'sharedSecret');
    }

    private function isLocalPublishUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'http'
            && in_array($parts['host'] ?? '', ['127.0.0.1', '::1', 'localhost'], true)
            && ($parts['path'] ?? '') === '/publish';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

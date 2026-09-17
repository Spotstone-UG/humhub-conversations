<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\notifications;

use humhub\helpers\Html;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\user\models\User;
use yii\helpers\Url;

/** One native HumHub notice, delivered by enabled web/push targets. */
final class ChatNotification extends BaseNotification
{
    public $moduleId = 'conversations';
    public $eventType = 'message';

    protected function category()
    {
        return new ChatNotificationCategory();
    }

    public function event(string $eventType): self
    {
        $this->eventType = $eventType;
        $this->payload = ['eventType' => $eventType];
        return $this;
    }

    public function getUrl(): string
    {
        if ($this->source instanceof ConversationMessage) {
            return $this->source->conversation->url . '#conversation-message-' . $this->source->id;
        }
        if ($this->source instanceof Conversation) {
            return $this->source->url;
        }
        return Url::to('/', true);
    }

    public function getSpaceId(): ?int
    {
        if ($this->source instanceof ConversationMessage) {
            return (int) $this->source->conversation->content->container->id;
        }
        return $this->source instanceof Conversation ? (int) $this->source->content->container->id : null;
    }

    public function isBlockedForUser(User $user): bool
    {
        if ($this->source instanceof ConversationMessage) {
            return !$this->source->conversation->content->canView($user);
        }
        return !$this->source instanceof Conversation || !$this->source->content->canView($user);
    }

    public function html(): string
    {
        $actor = $this->originator ? Html::encode($this->originator->displayName) : 'Das System';
        $chat = $this->source instanceof ConversationMessage ? $this->source->conversation : $this->source;
        $title = $chat instanceof Conversation ? Html::encode($chat->title) : '';
        $label = ($this->payload['eventType'] ?? $this->eventType) === 'created'
            ? 'hat einen neuen Chat gestartet:'
            : 'hat eine neue Nachricht geschrieben in:';
        return '<strong>' . $actor . '</strong> ' . Html::encode($label) . ' <strong>' . $title . '</strong>.';
    }

    public function getMailSubject(): string
    {
        return $this->text();
    }
}

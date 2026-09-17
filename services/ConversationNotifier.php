<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\notifications\ChatNotification;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

/** Sends native HumHub notifications; push modules can deliver them without a Firebase dependency here. */
final class ConversationNotifier
{
    public function notifyNewChat(Conversation $conversation, User $sender): void
    {
        $space = $conversation->content->container;
        if (!$space instanceof Space || !Yii::$app->has('notification')) {
            return;
        }
        $ids = Membership::find()->select('user_id')->where([
            'space_id' => $space->id,
            'status' => Membership::STATUS_MEMBER,
        ])->andWhere(['<>', 'user_id', $sender->id])->column();
        $this->send($conversation, $sender, array_map('intval', $ids), 'created');
    }

    public function notifyInterested(ConversationMessage $message, User $sender): void
    {
        if (!Yii::$app->has('notification')) {
            return;
        }
        $ids = (new ConversationInterestService())->recipientIds($message->conversation, $sender);
        $this->send($message, $sender, $ids, 'message');
    }

    /** @param int[] $ids */
    private function send($source, User $sender, array $ids, string $event): void
    {
        if ($ids === []) {
            return;
        }
        try {
            Yii::$app->notification->sendBulk(
                ChatNotification::instance()->from($sender)->about($source)->event($event),
                User::find()->where(['id' => array_values(array_unique($ids))]),
            );
        } catch (\Throwable $exception) {
            Yii::warning($exception->getMessage(), 'conversations.notification');
        }
    }
}

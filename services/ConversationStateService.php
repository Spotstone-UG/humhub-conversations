<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\models\ConversationReadReceipt;
use humhub\modules\conversations\models\ConversationUserSetting;
use humhub\modules\conversations\models\ConversationUserState;
use humhub\modules\user\models\User;
use Yii;

final class ConversationStateService
{
    /** Returns the private reading position before the current page updates it. */
    public function lastSeenMessageId(Conversation $conversation, User $user): ?int
    {
        $id = ConversationUserState::find()
            ->select('last_seen_message_id')
            ->where(['conversation_id' => $conversation->id, 'user_id' => $user->id])
            ->scalar();

        return $id === false || $id === null ? null : (int) $id;
    }

    public function unreadCount(Conversation $conversation, User $user): int
    {
        $lastSeenMessageId = $this->lastSeenMessageId($conversation, $user) ?? 0;

        return (int) ConversationMessage::find()
            ->where(['conversation_id' => $conversation->id])
            ->andWhere(['>', 'id', $lastSeenMessageId])
            ->count();
    }

    public function firstUnreadMessageId(Conversation $conversation, User $user): ?int
    {
        $lastSeenMessageId = $this->lastSeenMessageId($conversation, $user) ?? 0;

        $id = ConversationMessage::find()->select('id')
            ->where(['conversation_id' => $conversation->id])
            ->andWhere(['>', 'id', $lastSeenMessageId])
            ->orderBy(['id' => SORT_ASC])
            ->scalar();

        return $id === false || $id === null ? null : (int) $id;
    }

    /**
     * Stores personal state for every reader. A voluntary receipt is stored only
     * when this reader currently sends receipts, and only for other authors.
     */
    public function markSeen(Conversation $conversation, User $user, ?int $throughMessageId): void
    {
        if ($throughMessageId === null) {
            return;
        }

        Yii::$app->db->transaction(function () use ($conversation, $user, $throughMessageId): void {
            $previousId = (int) (ConversationUserState::find()
                ->select('last_seen_message_id')
                ->where(['conversation_id' => $conversation->id, 'user_id' => $user->id])
                ->scalar() ?? 0);

            $now = date('Y-m-d H:i:s');
            Yii::$app->db->createCommand()->upsert('{{%conversation_user_state}}', [
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'last_seen_message_id' => $throughMessageId,
                'last_opened_at' => $now,
            ], [
                'last_seen_message_id' => $throughMessageId,
                'last_opened_at' => $now,
            ])->execute();

            if (!ConversationUserSetting::readReceiptsEnabled($user)) {
                return;
            }

            $messages = ConversationMessage::find()
                ->where(['conversation_id' => $conversation->id])
                ->andWhere(['>', 'id', $previousId])
                ->andWhere(['<=', 'id', $throughMessageId])
                ->all();

            foreach ($messages as $message) {
                if ((int) $message->content->created_by === (int) $user->id) {
                    continue;
                }

                Yii::$app->db->createCommand()->upsert('{{%conversation_read_receipt}}', [
                    'message_id' => $message->id,
                    'user_id' => $user->id,
                    'read_at' => $now,
                ], false)->execute();
            }
        });
    }

    /** @return ConversationReadReceipt[] */
    public function visibleReadReceipts(ConversationMessage $message, User $sender): array
    {
        if ((int) $message->content->created_by !== (int) $sender->id || !ConversationUserSetting::readReceiptsEnabled($sender)) {
            return [];
        }

        return ConversationReadReceipt::find()
            ->alias('receipt')
            ->joinWith('user user')
            ->leftJoin('{{%conversation_user_setting}} setting', 'setting.user_id = receipt.user_id')
            ->where(['receipt.message_id' => $message->id])
            ->andWhere(['or', ['setting.read_receipts_enabled' => 1], ['setting.user_id' => null]])
            ->orderBy(['receipt.read_at' => SORT_ASC])
            ->all();
    }
}

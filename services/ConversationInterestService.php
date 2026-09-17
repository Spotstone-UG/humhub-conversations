<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationInterest;
use humhub\modules\user\models\User;
use Yii;

/** Keeps ongoing chat notices opt-in instead of turning every Space member into a follower. */
final class ConversationInterestService
{
    public function isInterested(Conversation $conversation, User $user): bool
    {
        return ConversationInterest::find()->where([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
        ])->exists();
    }

    /** Returns the new state: true means the person now follows this chat deliberately. */
    public function toggle(Conversation $conversation, User $user): bool
    {
        $interest = ConversationInterest::findOne(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
        if ($interest !== null) {
            $interest->delete();
            return false;
        }

        $interest = new ConversationInterest([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'interested_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$interest->save()) {
            throw new \yii\web\BadRequestHttpException('Das Interesse am Chat konnte nicht gespeichert werden.');
        }
        return true;
    }

    public function addCreator(Conversation $conversation, ?User $user): void
    {
        if ($user === null || $this->isInterested($conversation, $user)) {
            return;
        }
        $this->toggle($conversation, $user);
    }

    /** @return int[] active, interested users except the sender */
    public function recipientIds(Conversation $conversation, User $sender): array
    {
        return array_map('intval', ConversationInterest::find()
            ->alias('interest')
            ->joinWith('user user')
            ->select('interest.user_id')
            ->where(['interest.conversation_id' => $conversation->id])
            ->andWhere(['<>', 'interest.user_id', $sender->id])
            ->andWhere(['user.status' => User::STATUS_ENABLED])
            ->column());
    }
}

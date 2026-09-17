<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\models\ConversationReaction;
use humhub\modules\user\models\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

final class ConversationReactionService
{
    /** Toggles one of the explicitly allowed Unicode emoji reactions. */
    public function toggle(ConversationMessage $message, User $user, string $emoji): void
    {
        if (!$message->content->canView($user)) {
            throw new ForbiddenHttpException();
        }

        $reaction = ConversationReaction::findOne([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);

        if ($reaction !== null) {
            $reaction->delete();
            return;
        }

        $reaction = new ConversationReaction([
            'message_id' => $message->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$reaction->save()) {
            throw new BadRequestHttpException('Die Reaktion konnte nicht gespeichert werden.');
        }
    }

    /**
     * @param ConversationMessage[] $messages
     * @return array<int, array<string, array{emoji:string,count:int,mine:bool}>>
     */
    public function summaries(array $messages, User $user): array
    {
        $messageIds = array_map(static fn(ConversationMessage $message): int => (int) $message->id, $messages);
        if ($messageIds === []) {
            return [];
        }

        $summaries = [];
        foreach (ConversationReaction::find()->where(['message_id' => $messageIds])->all() as $reaction) {
            $messageId = (int) $reaction->message_id;
            $emoji = $reaction->emoji;
            $summaries[$messageId][$emoji] ??= ['emoji' => $emoji, 'count' => 0, 'mine' => false];
            $summaries[$messageId][$emoji]['count']++;
            $summaries[$messageId][$emoji]['mine'] = $summaries[$messageId][$emoji]['mine'] || (int) $reaction->user_id === (int) $user->id;
        }

        return $summaries;
    }
}

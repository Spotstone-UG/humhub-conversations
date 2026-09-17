<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\PostEmojiReaction;
use humhub\modules\post\models\Post;
use humhub\modules\user\models\User;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

final class PostEmojiReactionService
{
    /** Toggles a voluntary emoji reaction on a normal HumHub post. */
    public function toggle(Post $post, User $user, string $emoji): void
    {
        if (!$post->content->canView($user)) {
            throw new ForbiddenHttpException();
        }

        $reaction = PostEmojiReaction::findOne([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
        ]);
        if ($reaction !== null) {
            $reaction->delete();
            return;
        }

        $reaction = new PostEmojiReaction([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'emoji' => $emoji,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$reaction->save()) {
            throw new BadRequestHttpException('Die Reaktion konnte nicht gespeichert werden.');
        }
    }

    /** @return array<string, array{emoji:string,count:int,mine:bool}> */
    public function summary(Post $post, User $user): array
    {
        $summary = [];
        foreach (PostEmojiReaction::find()->where(['post_id' => $post->id])->all() as $reaction) {
            $emoji = $reaction->emoji;
            $summary[$emoji] ??= ['emoji' => $emoji, 'count' => 0, 'mine' => false];
            $summary[$emoji]['count']++;
            $summary[$emoji]['mine'] = $summary[$emoji]['mine'] || (int) $reaction->user_id === (int) $user->id;
        }

        return $summary;
    }
}

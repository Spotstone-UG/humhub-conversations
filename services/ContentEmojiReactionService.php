<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\components\behaviors\PolymorphicRelation;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\conversations\models\ContentEmojiReaction;
use humhub\modules\conversations\widgets\ContentEmojiReactionLink;
use humhub\modules\user\models\User;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/** Stores emoji reactions for every likeable HumHub content type. */
final class ContentEmojiReactionService
{
    public function toggle(ContentActiveRecord $content, User $user, string $emoji): void
    {
        if (!$content->content->canView($user) || !ContentEmojiReactionLink::supports($content)) {
            throw new ForbiddenHttpException();
        }

        $condition = $this->condition($content, $user, $emoji);
        $reaction = ContentEmojiReaction::findOne($condition);
        if ($reaction !== null) {
            $reaction->delete();
            return;
        }

        $reaction = new ContentEmojiReaction($condition + ['created_at' => date('Y-m-d H:i:s')]);
        if (!$reaction->save()) {
            throw new BadRequestHttpException('Die Reaktion konnte nicht gespeichert werden.');
        }
    }

    /** @return array<string, array{emoji:string,count:int,mine:bool}> */
    public function summary(ContentActiveRecord $content, User $user): array
    {
        $summary = [];
        foreach (ContentEmojiReaction::find()->where($this->contentCondition($content))->all() as $reaction) {
            $emoji = $reaction->emoji;
            $summary[$emoji] ??= ['emoji' => $emoji, 'count' => 0, 'mine' => false];
            $summary[$emoji]['count']++;
            $summary[$emoji]['mine'] = $summary[$emoji]['mine'] || (int) $reaction->user_id === (int) $user->id;
        }

        return $summary;
    }

    /** @return array{content_model:string,content_id:int,user_id:int,emoji:string} */
    private function condition(ContentActiveRecord $content, User $user, string $emoji): array
    {
        return $this->contentCondition($content) + ['user_id' => (int) $user->id, 'emoji' => $emoji];
    }

    /** @return array{content_model:string,content_id:int} */
    private function contentCondition(ContentActiveRecord $content): array
    {
        return [
            'content_model' => PolymorphicRelation::getObjectModel($content),
            'content_id' => (int) $content->getPrimaryKey(),
        ];
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\user\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** One voluntary emoji reaction to any likeable HumHub content record. */
final class ContentEmojiReaction extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'content_emoji_reaction';
    }

    public function rules(): array
    {
        return [
            [['content_model', 'content_id', 'user_id', 'emoji'], 'required'],
            [['content_id', 'user_id'], 'integer', 'min' => 1],
            [['content_model'], 'string', 'max' => 255],
            [['emoji'], 'string', 'max' => 32],
        ];
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\post\models\Post;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** One voluntary emoji reaction by one user to a normal HumHub post. */
final class PostEmojiReaction extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'post_emoji_reaction';
    }

    public function rules(): array
    {
        return [
            [['post_id', 'user_id', 'emoji'], 'required'],
            [['post_id', 'user_id'], 'integer', 'min' => 1],
            [['emoji'], 'string', 'max' => 32],
        ];
    }

    public function getPost(): ActiveQuery
    {
        return $this->hasOne(Post::class, ['id' => 'post_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

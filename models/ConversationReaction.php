<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\user\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** One voluntary emoji reaction by one user to one conversation message. */
final class ConversationReaction extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_reaction';
    }

    public function rules(): array
    {
        return [
            [['message_id', 'user_id', 'emoji'], 'required'],
            [['message_id', 'user_id'], 'integer', 'min' => 1],
            [['emoji'], 'string', 'max' => 32],
        ];
    }

    public function getMessage(): ActiveQuery
    {
        return $this->hasOne(ConversationMessage::class, ['id' => 'message_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

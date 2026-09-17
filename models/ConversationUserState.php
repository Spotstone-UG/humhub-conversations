<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\components\ActiveRecord;
use yii\db\ActiveQuery;

final class ConversationUserState extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_user_state';
    }

    public function rules(): array
    {
        return [
            [['conversation_id', 'user_id'], 'required'],
            [['conversation_id', 'user_id', 'last_seen_message_id'], 'integer'],
            [['last_opened_at'], 'safe'],
        ];
    }

    public function getConversation(): ActiveQuery
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversation_id']);
    }
}


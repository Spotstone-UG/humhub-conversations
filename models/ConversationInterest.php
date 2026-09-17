<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

/** A deliberate opt-in for ongoing notices from one chat. */
final class ConversationInterest extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_interest';
    }

    public function rules(): array
    {
        return [
            [['conversation_id', 'user_id'], 'required'],
            [['conversation_id', 'user_id'], 'integer', 'min' => 1],
            [['interested_at'], 'safe'],
        ];
    }

    public function getConversation(): ActiveQuery
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversation_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

final class ConversationReadReceipt extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_read_receipt';
    }

    public function rules(): array
    {
        return [
            [['message_id', 'user_id'], 'required'],
            [['message_id', 'user_id'], 'integer'],
            [['read_at'], 'safe'],
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


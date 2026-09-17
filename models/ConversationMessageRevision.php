<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\user\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** Immutable snapshot created whenever an author changes a message. */
final class ConversationMessageRevision extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_message_revision';
    }

    public function rules(): array
    {
        return [
            [['message_id', 'editor_id', 'previous_message', 'revised_message', 'edited_at'], 'required'],
            [['message_id', 'editor_id'], 'integer', 'min' => 1],
            [['previous_message', 'revised_message'], 'string', 'max' => 65535],
            [['edited_at'], 'safe'],
        ];
    }

    public function getMessage(): ActiveQuery
    {
        return $this->hasOne(ConversationMessage::class, ['id' => 'message_id']);
    }

    public function getEditor(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'editor_id']);
    }
}

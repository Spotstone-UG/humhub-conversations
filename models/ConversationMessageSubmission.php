<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** Immutable claim for one user-initiated message submission. */
final class ConversationMessageSubmission extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_message_submission';
    }

    public function rules(): array
    {
        return [
            [['conversation_id', 'user_id', 'submission_token', 'created_at'], 'required'],
            [['conversation_id', 'user_id', 'message_id'], 'integer', 'min' => 1],
            [['submission_token'], 'string', 'max' => 64],
            [['created_at'], 'safe'],
        ];
    }

    public function getMessage(): ActiveQuery
    {
        return $this->hasOne(ConversationMessage::class, ['id' => 'message_id']);
    }
}

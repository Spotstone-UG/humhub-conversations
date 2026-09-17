<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use yii\db\ActiveRecord;

/** A personal preference: this Space stays readable but does not raise chat unread badges. */
final class ConversationMutedSpace extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_muted_space';
    }

    public function rules(): array
    {
        return [
            [['user_id', 'space_id'], 'required'],
            [['user_id', 'space_id'], 'integer', 'min' => 1],
            [['muted_at'], 'safe'],
        ];
    }
}

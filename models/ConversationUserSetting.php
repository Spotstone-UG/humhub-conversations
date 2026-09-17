<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\components\ActiveRecord;
use humhub\modules\user\models\User;
use yii\db\ActiveQuery;

final class ConversationUserSetting extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_user_setting';
    }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'read_receipts_enabled', 'send_with_ctrl_enter', 'typing_indicators_enabled'], 'integer'],
        ];
    }

    public static function readReceiptsEnabled(User $user): bool
    {
        $setting = static::findOne(['user_id' => $user->id]);
        return $setting === null || (bool) $setting->read_receipts_enabled;
    }

    public static function sendWithCtrlEnter(User $user): bool
    {
        $setting = static::findOne(['user_id' => $user->id]);
        return $setting !== null && (bool) $setting->send_with_ctrl_enter;
    }

    public static function typingIndicatorsEnabled(User $user): bool
    {
        $setting = static::findOne(['user_id' => $user->id]);
        return $setting !== null && (bool) $setting->typing_indicators_enabled;
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

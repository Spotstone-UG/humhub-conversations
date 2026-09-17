<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\conversations\permissions\CreateConversation;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\Url;

final class ConversationMessage extends ContentActiveRecord
{
    /** Messages are content records for files/reactions, but never Stream entries. */
    protected $streamChannel = null;
    protected $moduleId = 'conversations';
    protected $createPermission = CreateConversation::class;
    public $autoFollow = false;

    public static function tableName(): string
    {
        return 'conversation_message';
    }

    public function rules(): array
    {
        return [
            [['conversation_id'], 'required'],
            [['conversation_id'], 'integer', 'min' => 1],
            [['message'], 'required'],
            [['message'], 'string', 'max' => 65535],
        ];
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {
            RichText::postProcess($this->message, $this, 'message');
            Conversation::updateAll(['last_message_at' => $this->content->created_at], ['id' => $this->conversation_id]);
        }
    }

    public function getConversation(): ActiveQuery
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversation_id']);
    }

    public function getReadReceipts(): ActiveQuery
    {
        return $this->hasMany(ConversationReadReceipt::class, ['message_id' => 'id']);
    }

    public function getContentName(): string
    {
        return Yii::t('ConversationsModule.base', 'Conversation message');
    }

    public function getContentDescription(): string
    {
        return (string) $this->message;
    }

    public function getUrl(): string
    {
        return Url::to(['/conversations/conversation/view', 'id' => $this->conversation_id, 'contentContainer' => $this->content->container]);
    }
}


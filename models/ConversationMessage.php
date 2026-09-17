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
            [['conversation_id', 'reply_to_message_id'], 'integer', 'min' => 1],
            [['message'], 'required'],
            [['message'], 'string', 'max' => 65535],
            [['deleted_at'], 'safe'],
        ];
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        RichText::postProcess($this->message, $this, 'message');

        if ($insert) {
            Conversation::updateAll(['last_message_at' => $this->content->created_at], ['id' => $this->conversation_id]);
            if (!Yii::$app->user->isGuest) {
                (new \humhub\modules\conversations\services\ConversationNotifier())->notifyInterested($this, Yii::$app->user->identity);
            }
        }
    }

    public function getConversation(): ActiveQuery
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversation_id']);
    }

    /** The message this reply refers to, always inside the same chat. */
    public function getReplyToMessage(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'reply_to_message_id']);
    }

    public function getReadReceipts(): ActiveQuery
    {
        return $this->hasMany(ConversationReadReceipt::class, ['message_id' => 'id']);
    }

    public function getReactions(): ActiveQuery
    {
        return $this->hasMany(ConversationReaction::class, ['message_id' => 'id']);
    }

    public function getRevisions(): ActiveQuery
    {
        return $this->hasMany(ConversationMessageRevision::class, ['message_id' => 'id'])
            ->orderBy(['edited_at' => SORT_DESC, 'id' => SORT_DESC]);
    }

    public function getContentName(): string
    {
        return Yii::t('ConversationsModule.base', 'Chat-Nachricht');
    }

    public function getContentDescription(): string
    {
        return $this->deleted_at === null ? (string) $this->message : 'Diese Nachricht wurde gelöscht.';
    }

    public function getUrl(): string
    {
        return Url::to(['/conversations/conversation/view', 'id' => $this->conversation_id, 'contentContainer' => $this->content->container]);
    }

    public function getIsDeleted(): bool
    {
        return $this->deleted_at !== null;
    }
}

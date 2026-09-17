<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\conversations\permissions\CreateConversation;
use humhub\modules\conversations\widgets\ConversationCard;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\Url;

final class Conversation extends ContentActiveRecord
{
    public $wallEntryClass = ConversationCard::class;
    protected $moduleId = 'conversations';
    protected $createPermission = CreateConversation::class;

    public static function tableName(): string
    {
        return 'conversation';
    }

    public function rules(): array
    {
        return [
            [['title'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['summary'], 'string', 'max' => 1000],
            [['last_message_at'], 'safe'],
            [['parent_conversation_id', 'origin_message_id'], 'integer', 'min' => 1],
            [['closed_at'], 'safe'],
            [['outcome'], 'string', 'max' => 4000],
        ];
    }

    public function getContentName(): string
    {
        return Yii::t('ConversationsModule.base', 'Conversation');
    }

    public function getContentDescription(): string
    {
        return (string) $this->summary;
    }

    public function getIcon(): string
    {
        return 'comments-o';
    }

    public function getUrl(): string
    {
        return Url::to(['/conversations/conversation/view', 'id' => $this->id, 'contentContainer' => $this->content->container]);
    }

    public function getMessages(): ActiveQuery
    {
        return $this->hasMany(ConversationMessage::class, ['conversation_id' => 'id'])->orderBy(['conversation_message.id' => SORT_ASC]);
    }

    public function getParentConversation(): ActiveQuery
    {
        return $this->hasOne(self::class, ['id' => 'parent_conversation_id']);
    }

    public function getSubconversations(): ActiveQuery
    {
        return $this->hasMany(self::class, ['parent_conversation_id' => 'id'])
            ->orderBy(['conversation.id' => SORT_ASC]);
    }

    public function getOriginMessage(): ActiveQuery
    {
        return $this->hasOne(ConversationMessage::class, ['id' => 'origin_message_id']);
    }

    public function getIsClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function beforeSave($insert)
    {
        // A subconversation is represented only by its card in the parent chat.
        // It must never become a second, unrelated Stream entry.
        $this->streamChannel = $this->parent_conversation_id === null ? 'default' : null;

        return parent::beforeSave($insert);
    }

    public function getUserStates(): ActiveQuery
    {
        return $this->hasMany(ConversationUserState::class, ['conversation_id' => 'id']);
    }

    public function getMessageCount(): int
    {
        return (int) $this->getMessages()->count();
    }
}

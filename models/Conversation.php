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

    public function getUserStates(): ActiveQuery
    {
        return $this->hasMany(ConversationUserState::class, ['conversation_id' => 'id']);
    }

    public function getMessageCount(): int
    {
        return (int) $this->getMessages()->count();
    }
}


<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\modules\content\widgets\stream\WallStreamEntryWidget;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\services\ConversationStateService;
use Yii;

final class ConversationCard extends WallStreamEntryWidget
{
    public $createRoute = '/conversations/conversation/create-form';
    public $createFormClass = ConversationForm::class;
    /** Keep HumHub's standard Beitrag composer first; Conversation is additive. */
    public $createFormSortOrder = 110;

    protected function renderBody(): string
    {
        ConversationAsset::register($this->view);
        $unreadCount = Yii::$app->user->isGuest ? 0 : (new ConversationStateService())->unreadCount($this->model, Yii::$app->user->identity);

        return $this->render('conversationCard', [
            'conversation' => $this->model,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * The complete stream card is rendered directly by renderBody().
     * In particular this intentionally omits HumHub's comment footer: replies
     * belong in the dedicated conversation view, never in the stream.
     */
    protected function renderContent(): string
    {
        return '';
    }

    public function getAttributes(): array
    {
        return ['class' => 'conversation-stream-entry'];
    }
}

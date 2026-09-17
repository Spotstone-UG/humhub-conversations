<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\modules\content\widgets\stream\StreamEntryWidget;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\services\ConversationStateService;
use Yii;

final class ConversationCard extends StreamEntryWidget
{
    protected function renderBody(): string
    {
        ConversationAsset::register($this->view);
        $unreadCount = Yii::$app->user->isGuest ? 0 : (new ConversationStateService())->unreadCount($this->model, Yii::$app->user->identity);

        return $this->render('conversationCard', [
            'conversation' => $this->model,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function getAttributes(): array
    {
        return ['class' => 'conversation-stream-entry'];
    }
}


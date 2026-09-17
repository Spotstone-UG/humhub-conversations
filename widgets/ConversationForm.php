<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\modules\content\widgets\WallCreateContentForm;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\models\Conversation;
use humhub\widgets\form\ActiveForm;

/** Stream composer form for starting a Conversation. */
final class ConversationForm extends WallCreateContentForm
{
    public $submitUrl = '/conversations/conversation/post';

    public function getRenderParams(array $additionalParams = []): array
    {
        return array_merge([
            'conversation' => new Conversation($this->contentContainer),
            'wallCreateContentForm' => $this,
        ], $additionalParams);
    }

    public function renderForm(): string
    {
        ConversationAsset::register($this->view);

        return $this->render('conversationForm', $this->getRenderParams());
    }

    public function renderActiveForm(ActiveForm $form): string
    {
        ConversationAsset::register($this->view);

        return $this->render('conversationForm', $this->getRenderParams(['form' => $form]));
    }

    public function run()
    {
        if (!(new Conversation($this->contentContainer))->content->canEdit()) {
            return '';
        }

        return parent::run();
    }
}

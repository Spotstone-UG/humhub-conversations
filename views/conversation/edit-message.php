<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\widgets\bootstrap\Button;
use humhub\widgets\form\ActiveForm;

/** @var ConversationMessage $message */
/** @var \humhub\modules\content\components\ContentContainerActiveRecord $contentContainer */
/** @var \humhub\modules\conversations\models\Conversation $conversation */
$submitUrl = $contentContainer->createUrl('/conversations/conversation/edit-message', [
    'conversationId' => $conversation->id,
    'messageId' => $message->id,
]);
?>
<div class="modal-dialog">
    <div class="modal-content">
        <?php $form = ActiveForm::begin(['action' => $submitUrl, 'acknowledge' => true]); ?>
            <div class="modal-header"><h4 class="modal-title">Nachricht bearbeiten</h4></div>
            <div class="modal-body">
                <?= $form->field($message, 'message')->widget(RichTextField::class, [
                    'id' => 'conversation-message-edit-' . $message->id,
                    'layout' => RichTextField::LAYOUT_BLOCK,
                    'pluginOptions' => ['maxHeight' => '300px'],
                    'placeholder' => 'Nachricht bearbeiten …',
                    'focus' => true,
                ])->label(false) ?>
                <p class="text-body-secondary small mb-0">Nach dem Speichern wird die Nachricht als bearbeitet markiert.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Abbrechen</button>
                <?= Button::accent('Speichern')->submit() ?>
            </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

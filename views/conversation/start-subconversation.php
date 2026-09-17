<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

/** @var \humhub\modules\conversations\models\Conversation $parent */
/** @var \humhub\modules\conversations\models\ConversationMessage $origin */
/** @var \humhub\modules\conversations\models\Conversation $subconversation */
$form = ActiveForm::begin(['action' => $contentContainer->createUrl('/conversations/conversation/start-subconversation', ['conversationId' => $parent->id, 'messageId' => $origin->id])]);
?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Unterthema beginnen</h4></div>
        <div class="modal-body">
            <p class="help-block">Es wird eine eigene, mit dieser Nachricht verknüpfte Unterhaltung gestartet.</p>
            <?= $form->field($subconversation, 'title')->textInput(['maxlength' => 255, 'autofocus' => true])->label('Thema') ?>
            <?= $form->field($subconversation, 'summary')->textarea(['rows' => 3, 'maxlength' => 1000])->label('Kurzfassung') ?>
        </div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::submitButton('Unterthema starten', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>
<?php ActiveForm::end(); ?>

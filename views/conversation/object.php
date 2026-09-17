<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
/** @var \humhub\modules\conversations\models\ConversationConsensusProposal $proposal */
?>
<?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/object', ['conversationId' => $conversation->id, 'proposalId' => $proposal->id]), 'post') ?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Widerspruch erklären</h4></div>
        <div class="modal-body">
            <p class="help-block">Ein Widerspruch ist willkommen, wenn er hilft, das Ergebnis besser zu machen. Er erscheint für alle Teilnehmenden und kann einen Alternativvorschlag auslösen.</p>
            <?= Html::label('Was ist dein schwerwiegender Widerspruch?', 'conversation-objection-reason', ['class' => 'control-label']) ?>
            <?= Html::textarea('reason', '', ['id' => 'conversation-objection-reason', 'class' => 'form-control', 'rows' => 4, 'maxlength' => 2000, 'required' => true]) ?>
        </div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::submitButton('Schwerwiegenden Einwand festhalten', ['class' => 'btn btn-danger']) ?>
        </div>
    </div>
</div>
<?= Html::endForm() ?>

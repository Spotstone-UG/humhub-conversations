<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
/** @var \humhub\modules\space\models\Space $contentContainer */
?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content conversation-reopen-objection">
        <div class="modal-header">
            <h5 class="modal-title">Schwerwiegenden Einwand einbringen</h5>
            <?= Html::button('×', ['class' => 'btn-close', 'data-bs-dismiss' => 'modal', 'aria-label' => 'Schließen']) ?>
        </div>
        <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/reopen', ['conversationId' => $conversation->id]), 'post') ?>
        <div class="modal-body">
            <p>Damit wird der Chat wieder geöffnet. Der bisherige Konsent bleibt im Verlauf mit deinem Einwand nachvollziehbar.</p>
            <?= Html::label('Was ist dein schwerwiegender Einwand?', 'conversation-reopen-reason', ['class' => 'control-label']) ?>
            <?= Html::textarea('reason', '', ['id' => 'conversation-reopen-reason', 'class' => 'form-control', 'rows' => 4, 'maxlength' => 2000, 'required' => true]) ?>
        </div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::submitButton('Chat mit Einwand wieder öffnen', ['class' => 'btn btn-danger']) ?>
        </div>
        <?= Html::endForm() ?>
    </div>
</div>

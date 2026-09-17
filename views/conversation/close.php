<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
?>
<?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/close', ['conversationId' => $conversation->id]), 'post') ?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Unterhaltung beenden</h4></div>
        <div class="modal-body">
            <p class="help-block">Das Gespräch bleibt lesbar. Das Ergebnis erscheint beim späteren Öffnen als Erstes.</p>
            <?= Html::label('Gesprächsergebnis / Konsens', 'conversation-outcome', ['class' => 'control-label']) ?>
            <?= Html::textarea('outcome', $conversation->outcome, ['id' => 'conversation-outcome', 'class' => 'form-control', 'rows' => 5, 'maxlength' => 4000, 'placeholder' => 'Was wurde beschlossen oder offen gelassen?']) ?>
        </div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::submitButton('Unterhaltung beenden', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>
<?= Html::endForm() ?>

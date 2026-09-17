<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
/** @var \humhub\modules\conversations\models\ConversationConsensusProposal $proposal */
?>
<?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/alternative-proposal', ['conversationId' => $conversation->id, 'proposalId' => $proposal->id]), 'post') ?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Alternativvorschlag</h4></div>
        <div class="modal-body">
            <p class="help-block">Der Vorschlag ersetzt das aktuelle Gesprächsergebnis als neue Fassung und startet eine neue Konsensrunde.</p>
            <?= Html::label('Neuer Ergebnisvorschlag', 'alternative-proposal-body', ['class' => 'control-label']) ?>
            <?= Html::textarea('body', '', ['id' => 'alternative-proposal-body', 'class' => 'form-control', 'rows' => 5, 'maxlength' => 4000, 'required' => true]) ?>
        </div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::submitButton('Vorschlag zur Abstimmung stellen', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>
<?= Html::endForm() ?>

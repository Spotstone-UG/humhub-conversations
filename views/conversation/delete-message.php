<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
/** @var \humhub\modules\conversations\models\ConversationMessage $message */
?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Nachricht löschen?</h4></div>
        <div class="modal-body"><p>Text und Anhänge sind danach für alle Personen entfernt. In der Unterhaltung bleibt nur der Hinweis <em>„Diese Nachricht wurde gelöscht.“</em>.</p></div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/delete-message', ['conversationId' => $conversation->id, 'messageId' => $message->id]), 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Nachricht löschen', ['class' => 'btn btn-danger']) ?>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>

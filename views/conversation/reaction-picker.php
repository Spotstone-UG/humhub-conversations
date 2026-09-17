<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var array<string, string> $emojis unicode => name */
/** @var \humhub\modules\conversations\models\Conversation $conversation */
/** @var \humhub\modules\conversations\models\ConversationMessage $message */
/** @var \humhub\modules\content\components\ContentContainerActiveRecord $contentContainer */
$submitUrl = $contentContainer->createUrl('/conversations/conversation/react', ['conversationId' => $conversation->id, 'messageId' => $message->id]);
?>
<div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content conversation-reaction-picker">
        <div class="modal-header"><h4 class="modal-title">Reaktion auswählen</h4></div>
        <div class="modal-body">
            <label class="visually-hidden" for="conversation-reaction-search">Emoji suchen</label>
            <input class="form-control mb-3" id="conversation-reaction-search" type="search" placeholder="Emoji suchen …" data-conversation-reaction-search>
            <p class="text-body-secondary small">Alle Emojis aus dem in HumHub vorhandenen Unicode-Katalog.</p>
            <div class="conversation-reaction-picker__grid" data-conversation-reaction-grid>
                <?php foreach ($emojis as $emoji => $name): ?>
                    <?= Html::beginForm($submitUrl, 'post', ['class' => 'conversation-reaction-picker__form', 'data-conversation-reaction-name' => $name]) ?>
                        <?= Html::hiddenInput('emoji', $emoji) ?>
                        <?= Html::submitButton($emoji, ['class' => 'conversation-reaction-picker__emoji', 'title' => $name, 'aria-label' => $name]) ?>
                    <?= Html::endForm() ?>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Schließen</button></div>
    </div>
</div>

<?php
// SPDX-License-Identifier: AGPL-3.0-only
use humhub\helpers\Html;
/** @var \humhub\modules\conversations\models\Conversation $conversation */
?>
<?php if ($conversation->content->canArchive()): ?>
    <?= Html::beginForm($conversation->content->container->createUrl('/conversations/conversation/set-archived', ['conversationId' => $conversation->id]), 'post', ['class' => 'conversation-archive-control']) ?>
        <?= Html::hiddenInput('archived', $conversation->content->archived ? '0' : '1') ?>
        <?= Html::submitButton($conversation->content->archived ? 'Wiederherstellen' : 'Archivieren', ['class' => 'btn btn-default btn-sm']) ?>
    <?= Html::endForm() ?>
<?php endif; ?>
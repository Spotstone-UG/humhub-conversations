<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\widgets\ConversationCard;

/** @var \humhub\modules\conversations\models\Conversation[] $conversations */
?>
<div class="panel panel-default conversation-index">
    <div class="panel-heading d-flex justify-content-between align-items-center">
        <strong>Conversations</strong>
        <?= Html::a('Neue Conversation', $contentContainer->createUrl('/conversations/conversation/create'), ['class' => 'btn btn-primary btn-sm']) ?>
    </div>
    <div class="panel-body">
        <?php if ($conversations === []): ?>
            <p class="text-body-secondary mb-0">Noch keine Conversations in diesem Space.</p>
        <?php endif; ?>
        <?php foreach ($conversations as $conversation): ?>
            <?= ConversationCard::widget(['model' => $conversation]) ?>
        <?php endforeach; ?>
    </div>
</div>


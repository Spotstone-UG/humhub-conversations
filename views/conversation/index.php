<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\assets\ConversationAsset;
/** @var \humhub\modules\conversations\models\Conversation[] $conversations */
ConversationAsset::register($this);
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
        <?php if ($conversations !== []): ?>
            <div class="conversation-overview" role="table" aria-label="Konversationsübersicht">
                <div class="conversation-overview__head" role="row">
                    <span>Unterhaltung</span><span>Status</span><span>Letzte Aktivität</span><span>Ergebnis</span>
                </div>
                <?php foreach ($conversations as $conversation): ?>
                    <article class="conversation-overview__row <?= $conversation->parent_conversation_id !== null ? 'conversation-overview__row--sub' : 'conversation-overview__row--parent' ?>" role="row">
                        <div class="conversation-overview__topic">
                            <?php if ($conversation->parent_conversation_id !== null): ?><span aria-hidden="true">↳ </span><?php endif; ?>
                            <?= Html::a(Html::encode($conversation->title), $conversation->url) ?>
                            <?php if ($conversation->parent_conversation_id !== null && $conversation->parentConversation !== null): ?>
                                <small class="conversation-overview__parent-link">Unterthema zu: <?= Html::encode($conversation->parentConversation->title) ?></small>
                            <?php elseif ($conversation->getSubconversations()->count() > 0): ?>
                                <small class="conversation-overview__subcount"><?= $conversation->getSubconversations()->count() ?> Unterthemen</small>
                            <?php endif; ?>
                            <?php if ($conversation->summary): ?><small><?= Html::encode($conversation->summary) ?></small><?php endif; ?>
                        </div>
                        <div><span class="conversation-status <?= $conversation->isClosed ? 'conversation-status--closed' : '' ?>"><?= $conversation->isClosed ? 'Beendet' : 'Offen' ?></span></div>
                        <div><?= Yii::$app->formatter->asRelativeTime($conversation->last_message_at ?: $conversation->content->created_at) ?></div>
                        <div class="conversation-overview__outcome"><?= $conversation->isClosed && $conversation->outcome !== '' ? Html::encode($conversation->outcome) : '—' ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

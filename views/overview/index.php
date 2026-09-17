<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\assets\ConversationAsset;

/** @var array<int, array{space: \humhub\modules\space\models\Space, conversations: \humhub\modules\conversations\models\Conversation[], unreadCounts: array<int, int>}> $groups */
ConversationAsset::register($this);
?>
<div class="container conversation-global-overview">
    <div class="panel panel-default conversation-index">
        <div class="panel-heading">
            <strong>Chats</strong>
            <span class="text-body-secondary">Alle Chats aus Spaces, die du lesen darfst.</span>
        </div>
        <div class="panel-body">
            <?php if ($groups === []): ?>
                <p class="text-body-secondary mb-0">Für dich sind noch keine Chats sichtbar.</p>
            <?php endif; ?>

            <?php foreach ($groups as $group): ?>
                <?php $space = $group['space']; ?>
                <section class="conversation-global-overview__space">
                    <h2>
                        <i class="fa fa-users" aria-hidden="true"></i>
                        <?= Html::a(Html::encode($space->displayName), $space->url) ?>
                    </h2>
                    <div class="conversation-overview" role="table" aria-label="Chats in <?= Html::encode($space->displayName) ?>">
                        <div class="conversation-overview__head" role="row">
                            <span>Chat</span><span>Status</span><span>Letzte Aktivität</span><span>Ergebnis</span>
                        </div>
                        <?php foreach ($group['conversations'] as $conversation): ?>
                            <?php $unread = $group['unreadCounts'][(int) $conversation->id] ?? 0; ?>
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
                                <div>
                                    <span class="conversation-status <?= $conversation->isClosed ? 'conversation-status--closed' : '' ?>"><?= $conversation->isClosed ? 'Beendet' : 'Offen' ?></span>
                                    <?php if ($unread > 0): ?><span class="conversation-overview__unread">● <?= $unread ?> neu</span><?php endif; ?>
                                </div>
                                <div><?= Yii::$app->formatter->asRelativeTime($conversation->last_message_at ?: $conversation->content->created_at) ?></div>
                                <div class="conversation-overview__outcome"><?= $conversation->isClosed && $conversation->outcome !== '' ? Html::encode($conversation->outcome) : '—' ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\models\ConversationInterest;

/** @var array{space: \humhub\modules\space\models\Space, conversations: \humhub\modules\conversations\models\Conversation[], unreadCounts: array<int, int>, isMuted: bool} $group */
$space = $group['space'];
$muted = $group['isMuted'];
?>
<section class="conversation-global-overview__space<?= $muted ? ' conversation-global-overview__space--muted' : '' ?>">
    <header class="conversation-global-overview__space-header">
        <h2>
            <i class="fa fa-users" aria-hidden="true"></i>
            <?= Html::a(Html::encode($space->displayName), $space->url) ?>
        </h2>
        <?= Html::beginForm(['/conversations/overview/toggle-mute', 'spaceId' => $space->id], 'post', ['class' => 'conversation-global-overview__mute-form']) ?>
            <?= Html::submitButton($muted ? 'Stummschaltung aufheben' : 'Space stummschalten', ['class' => 'btn btn-default btn-sm']) ?>
        <?= Html::endForm() ?>
    </header>
    <div class="conversation-overview" role="table" aria-label="Chats in <?= Html::encode($space->displayName) ?>">
        <div class="conversation-overview__head" role="row">
            <span>Chat</span><span>Status</span><span>Letzte Aktivität</span><span>Ergebnis</span><span class="conversation-overview__interest-heading">Hinweise</span>
        </div>
        <?php foreach ($group['conversations'] as $conversation): ?>
            <?php $unread = $group['unreadCounts'][(int) $conversation->id] ?? 0; ?>
            <?php $interested = ConversationInterest::find()->where(['conversation_id' => $conversation->id, 'user_id' => $currentUser->id])->exists(); ?>
            <article class="conversation-overview__row <?= $conversation->parent_conversation_id !== null ? 'conversation-overview__row--sub' : 'conversation-overview__row--parent' ?>" role="row" data-conversation-status="<?= $conversation->isClosed ? 'closed' : 'open' ?>" data-conversation-unread="<?= $unread > 0 ? 'true' : 'false' ?>" data-conversation-interest="<?= $interested ? 'true' : 'false' ?>">
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
                    <?php if ($unread > 0 && !$muted): ?><span class="conversation-overview__unread">● <?= $unread ?> neu</span><?php endif; ?>
                </div>
                <div><?= Yii::$app->formatter->asRelativeTime($conversation->last_message_at ?: $conversation->content->created_at) ?></div>
                <div class="conversation-overview__outcome"><?= $conversation->isClosed && $conversation->outcome !== '' ? Html::encode($conversation->outcome) : '—' ?></div>
                <div class="conversation-overview__interest-control">
                    <?= Html::beginForm(['/conversations/conversation/toggle-interest', 'conversationId' => $conversation->id, 'cguid' => $space->guid], 'post') ?>
                        <?= Html::submitButton(
                            Html::tag('i', '', ['class' => 'fa ' . ($interested ? 'fa-bell' : 'fa-bell-slash-o'), 'aria-hidden' => 'true']) . Html::tag('span', $interested ? 'Hinweise an' : 'Hinweise aus', ['class' => 'visually-hidden']),
                            [
                                'class' => 'btn btn-default btn-sm conversation-overview__interest-toggle' . ($interested ? ' is-interested' : ' is-not-interested'),
                                'title' => $interested ? 'Dieser Chat interessiert dich. Hinweise ausschalten.' : 'Dieser Chat interessiert dich nicht. Hinweise einschalten.',
                                'aria-label' => $interested ? 'Dieser Chat interessiert dich. Hinweise ausschalten.' : 'Dieser Chat interessiert dich nicht. Hinweise einschalten.',
                            ]
                        ) ?>
                    <?= Html::endForm() ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

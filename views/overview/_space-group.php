<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\models\ConversationInterest;
use humhub\modules\space\widgets\Image;

/** @var array{space: \humhub\modules\space\models\Space, conversations: \humhub\modules\conversations\models\Conversation[], unreadCounts: array<int, int>, isMuted: bool} $group */
$space = $group['space'];
$muted = $group['isMuted'];
?>
<section role="rowgroup" class="conversation-global-overview__space<?= $muted ? ' conversation-global-overview__space--muted' : '' ?>">
        <?php foreach ($group['conversations'] as $conversation): ?>
            <?php $unread = $group['unreadCounts'][(int) $conversation->id] ?? 0; ?>
            <?php $interested = ConversationInterest::find()->where(['conversation_id' => $conversation->id, 'user_id' => $currentUser->id])->exists(); ?>
            <article class="conversation-overview__row <?= $conversation->parent_conversation_id !== null ? 'conversation-overview__row--sub' : 'conversation-overview__row--parent' ?>" role="row" data-conversation-status="<?= $conversation->isClosed ? 'closed' : 'open' ?>" data-conversation-unread="<?= $unread > 0 ? 'true' : 'false' ?>" data-conversation-interest="<?= $interested ? 'true' : 'false' ?>">
                <div class="conversation-overview__topic">
                    <span class="conversation-overview__space-image" title="<?= Html::encode($space->displayName) ?>"><?= Image::widget(['space' => $space, 'width' => 36, 'link' => true, 'showTooltip' => false]) ?></span>
                    <div class="conversation-overview__topic-text">
                    <?php if ($conversation->parent_conversation_id !== null): ?><span aria-hidden="true">↳ </span><?php endif; ?>
                    <?= Html::a(Html::encode($conversation->title), $conversation->url) ?>
                    <?php if ($conversation->parent_conversation_id !== null && $conversation->parentConversation !== null): ?>
                        <small class="conversation-overview__parent-link">Unterthema zu: <?= Html::encode($conversation->parentConversation->title) ?></small>
                    <?php elseif ($conversation->getSubconversations()->count() > 0): ?>
                        <small class="conversation-overview__subcount"><?= $conversation->getSubconversations()->count() ?> Unterthemen</small>
                    <?php endif; ?>
                    <?php if ($conversation->summary): ?><small><?= Html::encode($conversation->summary) ?></small><?php endif; ?>
                    <small class="conversation-overview__space-name"><?= Html::encode($space->displayName) ?></small>
                    <?= $this->render('../conversation/_archive-control', ['conversation' => $conversation]) ?>
                    </div>
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
                    <?= Html::beginForm(['/conversations/overview/toggle-mute', 'spaceId' => $space->id], 'post', ['class' => 'conversation-overview__space-mute']) ?>
                        <?= Html::submitButton('<i class="fa fa-volume-off" aria-hidden="true"></i>', ['class' => 'btn btn-link btn-sm', 'title' => $muted ? 'Space-Stummschaltung aufheben' : 'Space stummschalten', 'aria-label' => ($muted ? 'Stummschaltung aufheben: ' : 'Space stummschalten: ') . $space->displayName]) ?>
                    <?= Html::endForm() ?>
                </div>
            </article>
        <?php endforeach; ?>
</section>

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
?>
<article class="conversation-card">
    <div class="conversation-card__main">
        <h3 class="conversation-card__title"><?= Html::a(Html::encode($conversation->title), $conversation->url) ?></h3>
        <?php if ($conversation->summary !== null && $conversation->summary !== ''): ?>
            <p class="conversation-card__summary"><?= Html::encode($conversation->summary) ?></p>
        <?php endif; ?>
    </div>
    <footer class="conversation-card__meta">
        <span><?= Html::encode($conversation->content->container->displayName) ?></span>
        <span aria-hidden="true">·</span>
        <span><?= Yii::$app->formatter->asRelativeTime($conversation->last_message_at ?: $conversation->content->created_at) ?></span>
        <span class="conversation-card__counts"><span aria-label="Nachrichten">💬 <?= $conversation->messageCount ?></span>
            <?php if ($unreadCount > 0): ?><span class="conversation-card__unread">● <?= $unreadCount ?> neu</span><?php endif; ?>
        </span>
    </footer>
</article>

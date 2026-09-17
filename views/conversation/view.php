<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\models\ConversationUserSetting;
use humhub\modules\conversations\services\ConversationStateService;
use humhub\modules\file\widgets\ShowFiles;
use humhub\modules\file\widgets\Upload;
use humhub\modules\like\widgets\LikeLink;

ConversationAsset::register($this);
$stateService = new ConversationStateService();
$currentUser = Yii::$app->user->identity;
$uploads = Upload::withName('fileList[]');
?>
<section class="conversation-view">
    <header class="conversation-view__header">
        <?= Html::a('← ' . Html::encode($contentContainer->displayName), $contentContainer->createUrl('/conversations/conversation/index'), ['class' => 'conversation-view__back']) ?>
        <h1><?= Html::encode($conversation->title) ?></h1>
        <?php if ($conversation->summary): ?><p><?= Html::encode($conversation->summary) ?></p><?php endif; ?>
    </header>

    <div class="conversation-view__messages" aria-live="polite">
        <?php if ($messages === []): ?>
            <p class="text-body-secondary text-center">Noch keine Nachrichten. Starte die Conversation.</p>
        <?php endif; ?>
        <?php foreach ($messages as $message): ?>
            <?php if ($firstUnreadMessageId !== null && $message->id === $firstUnreadMessageId && $unreadCount > 0): ?>
                <div class="conversation-unread-divider" id="first-unread-message">── <?= $unreadCount ?> neue Nachrichten ──</div>
            <?php endif; ?>
            <?php $isOwn = (int) $message->content->created_by === (int) $currentUser->id; ?>
            <article class="conversation-message <?= $isOwn ? 'conversation-message--own' : '' ?>" id="conversation-message-<?= $message->id ?>">
                <div class="conversation-message__author"><?= Html::encode($message->content->createdBy->displayName) ?></div>
                <div class="conversation-message__bubble">
                    <?= RichText::output($message->message, ['record' => $message]) ?>
                    <?= ShowFiles::widget(['object' => $message]) ?>
                </div>
                <div class="conversation-message__meta">
                    <time datetime="<?= Html::encode($message->content->created_at) ?>"><?= Yii::$app->formatter->asTime($message->content->created_at, 'short') ?></time>
                    <?php if ($isOwn): ?>
                        <?php $receipts = $stateService->visibleReadReceipts($message, $currentUser); ?>
                        <?php if ($receipts !== []): ?>
                            <?= Html::a('✓✓', $contentContainer->createUrl('/conversations/conversation/receipts', ['conversationId' => $conversation->id, 'messageId' => $message->id]), ['class' => 'conversation-message__status conversation-message__status--read', 'title' => 'Von mindestens einer Person gelesen', 'data-bs-target' => '#globalModal']) ?>
                        <?php elseif (ConversationUserSetting::readReceiptsEnabled($currentUser)): ?>
                            <span class="conversation-message__status" title="Veröffentlicht und verfügbar">✓✓</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="conversation-message__actions">
                    <?php if (Yii::$app->getModule('like') !== null): ?><?= LikeLink::widget(['object' => $message]) ?><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/message', ['conversationId' => $conversation->id]), 'post', ['class' => 'conversation-composer']) ?>
        <?= Html::textarea('ConversationMessage[message]', '', ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Nachricht schreiben …', 'required' => true]) ?>
        <div class="conversation-composer__controls">
            <div><?= $uploads->button() ?><?= $uploads->progress() ?><?= $uploads->preview() ?></div>
            <?= Html::submitButton('Senden', ['class' => 'btn btn-primary']) ?>
        </div>
    <?= Html::endForm() ?>
</section>

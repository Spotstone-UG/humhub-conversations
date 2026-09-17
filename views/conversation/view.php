<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\models\ConversationUserSetting;
use humhub\modules\conversations\services\ConversationStateService;
use humhub\modules\conversations\services\ConversationReactionService;
use humhub\modules\conversations\models\ConversationConsensusResponse;
use humhub\modules\file\widgets\ShowFiles;
use humhub\modules\file\widgets\Upload;

ConversationAsset::register($this);
$stateService = new ConversationStateService();
$currentUser = Yii::$app->user->identity;
$uploads = Upload::withName('fileList[]');
$reactionSummaries = (new ConversationReactionService())->summaries($messages, $currentUser);
$avatar = static function ($user): string {
    $words = preg_split('/\s+/u', trim((string) $user->displayName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = mb_strtoupper(mb_substr((string) ($words[0] ?? '?'), 0, 1));
    if (count($words) > 1) {
        $initials .= mb_strtoupper(mb_substr((string) end($words), 0, 1));
    }
    $hue = ((int) $user->id * 47) % 360;

    if ($user->getProfileImage()->hasImage()) {
        return Html::img($user->getProfileImage()->getUrl(), [
            'class' => 'conversation-avatar__image',
            'alt' => 'Profilbild von ' . $user->displayName,
        ]);
    }

    return Html::tag('span', Html::encode($initials), [
        'class' => 'conversation-avatar__fallback',
        'style' => '--conversation-avatar-hue: ' . $hue,
        'aria-label' => $user->displayName,
    ]);
};
?>
<section class="conversation-view">
    <header class="conversation-view__header">
        <?php if ($conversation->parentConversation !== null): ?>
            <?= Html::a('← Zur Hauptkonversation: ' . Html::encode($conversation->parentConversation->title), $conversation->parentConversation->url, ['class' => 'conversation-view__back']) ?>
        <?php else: ?>
            <?= Html::a('← ' . Html::encode($contentContainer->displayName), $contentContainer->createUrl('/conversations/conversation/index'), ['class' => 'conversation-view__back']) ?>
        <?php endif; ?>
        <h1><?= Html::encode($conversation->title) ?></h1>
        <?php if ($conversation->summary): ?><p><?= Html::encode($conversation->summary) ?></p><?php endif; ?>
        <div class="conversation-view__lifecycle">
            <?php if ($conversation->isClosed): ?>
                <span class="conversation-status conversation-status--closed">Beendet<?= $conversation->closedBy !== null ? ' von ' . Html::encode($conversation->closedBy->displayName) : '' ?></span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($conversation->isClosed): ?>
        <section class="conversation-outcome" id="conversation-consensus" aria-label="Gesprächsergebnis und Konsens">
            <span class="conversation-outcome__label">Gesprächsergebnis</span>
            <p><?= $conversation->outcome !== '' ? nl2br(Html::encode($conversation->outcome)) : 'Für diese Unterhaltung wurde kein Ergebnis festgehalten.' ?></p>
            <?php if ($consensus['proposal'] !== null): ?>
                <div class="conversation-consensus conversation-consensus--<?= Html::encode($consensus['status']) ?>">
                    <strong>Konsens</strong>
                    <?php if ($consensus['proposal']->supersedes_proposal_id !== null): ?>
                        <span class="conversation-consensus__version">Aktuelle Fassung: Alternativvorschlag</span>
                    <?php endif; ?>
                    <?php if ($consensus['status'] === 'confirmed_all'): ?>
                        <p>Bestätigt: Alle Teilnehmenden haben zugestimmt.</p>
                    <?php elseif ($consensus['status'] === 'confirmed_timeout'): ?>
                        <p>Bestätigt: Nach 14 Tagen gab es keine schwerwiegenden Widersprüche.</p>
                    <?php elseif ($consensus['status'] === 'objection'): ?>
                        <p>Es gibt einen Widerspruch. Ein Alternativvorschlag kann eine neue Konsensrunde starten.</p>
                    <?php else: ?>
                        <p><?= $consensus['consentCount'] ?> von <?= count($consensus['participants']) ?> Teilnehmenden haben zugestimmt. Rückmeldungen sind bis <?= Yii::$app->formatter->asDate($consensus['deadline'], 'long') ?> möglich.</p>
                    <?php endif; ?>
                    <?php if ($consensus['canRespond']): ?>
                        <div class="conversation-consensus__actions">
                            <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/consensus', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]), 'post') ?>
                                <?= Html::hiddenInput('decision', ConversationConsensusResponse::DECISION_CONSENT) ?>
                                <?= Html::submitButton(($consensus['responses'][(int) $currentUser->id] ?? null) === ConversationConsensusResponse::DECISION_CONSENT ? 'Zustimmung erteilt' : 'Zustimmen', ['class' => 'btn btn-success btn-sm']) ?>
                            <?= Html::endForm() ?>
                            <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/consensus', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]), 'post') ?>
                                <?= Html::hiddenInput('decision', ConversationConsensusResponse::DECISION_OBJECTION) ?>
                                <?= Html::submitButton('Widerspruch', ['class' => 'btn btn-default btn-sm']) ?>
                            <?= Html::endForm() ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($consensus['canProposeAlternative']): ?>
                        <?= Html::a('Alternativvorschlag machen', '#', [
                            'class' => 'conversation-consensus__alternative',
                            'data-action-click' => 'ui.modal.load',
                            'data-action-url' => $contentContainer->createUrl('/conversations/conversation/alternative-proposal', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]),
                        ]) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($conversation->content->canEdit()): ?>
                <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/reopen', ['conversationId' => $conversation->id]), 'post', ['class' => 'conversation-outcome__reopen']) ?>
                    <?= Html::submitButton('Unterhaltung wieder öffnen', ['class' => 'btn btn-default btn-sm']) ?>
                <?= Html::endForm() ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="conversation-view__messages" aria-live="polite">
        <?php if ($messages === []): ?>
            <p class="text-body-secondary text-center">Noch keine Nachrichten. Starte den Chat.</p>
        <?php endif; ?>
        <?php foreach ($messages as $message): ?>
            <?php if ($firstUnreadMessageId !== null && $message->id === $firstUnreadMessageId && $unreadCount > 0): ?>
                <div class="conversation-unread-divider" id="first-unread-message">── <?= $unreadCount ?> neue Nachrichten ──</div>
            <?php endif; ?>
            <?php $isOwn = (int) $message->content->created_by === (int) $currentUser->id; ?>
            <article class="conversation-message <?= $isOwn ? 'conversation-message--own' : '' ?> <?= $message->isDeleted ? 'conversation-message--deleted' : '' ?>" id="conversation-message-<?= $message->id ?>">
                <div class="conversation-message__author">
                    <span class="conversation-avatar"><?= $avatar($message->content->createdBy) ?></span>
                    <span><?= Html::encode($message->content->createdBy->displayName) ?></span>
                </div>
                <div class="conversation-message__bubble <?= !$message->isDeleted && $conversation->content->canEdit() ? 'conversation-message__bubble--has-menu' : '' ?>">
                    <?php if ($message->isDeleted): ?>
                        <em>Diese Nachricht wurde gelöscht.</em>
                    <?php else: ?>
                        <?= RichText::output($message->message, ['record' => $message]) ?>
                        <?= ShowFiles::widget(['object' => $message]) ?>
                    <?php endif; ?>
                    <?php if (!$message->isDeleted && $conversation->content->canEdit()): ?>
                        <span class="dropdown conversation-message__menu">
                            <?= Html::a('⋮', '#', ['class' => 'conversation-message__menu-trigger', 'data-bs-toggle' => 'dropdown', 'role' => 'button', 'aria-label' => 'Optionen für Nachricht', 'title' => 'Optionen']) ?>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><?= Html::a('Unterthema beginnen', '#', [
                                    'class' => 'dropdown-item',
                                    'data-action-click' => 'ui.modal.load',
                                    'data-action-url' => $contentContainer->createUrl('/conversations/conversation/start-subconversation', ['conversationId' => $conversation->id, 'messageId' => $message->id]),
                                ]) ?></li>
                                <?php if ($isOwn): ?>
                                    <li><?= Html::a('Bearbeiten', '#', [
                                        'class' => 'dropdown-item',
                                        'data-action-click' => 'ui.modal.load',
                                        'data-action-url' => $contentContainer->createUrl('/conversations/conversation/edit-message', ['conversationId' => $conversation->id, 'messageId' => $message->id]),
                                    ]) ?></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><?= Html::a('Nachricht löschen', '#', [
                                        'class' => 'dropdown-item text-danger',
                                        'data-action-click' => 'ui.modal.load',
                                        'data-action-url' => $contentContainer->createUrl('/conversations/conversation/delete-message', ['conversationId' => $conversation->id, 'messageId' => $message->id]),
                                    ]) ?></li>
                                <?php endif; ?>
                            </ul>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="conversation-message__meta">
                    <time datetime="<?= Html::encode($message->content->created_at) ?>"><?= Yii::$app->formatter->asTime($message->content->created_at, 'short') ?></time>
                    <?php if ($isOwn): ?>
                        <?php $receipts = $stateService->visibleReadReceipts($message, $currentUser); ?>
                        <?php if ($receipts !== []): ?>
                            <?= Html::a('✓✓', $contentContainer->createUrl('/conversations/conversation/receipts', ['conversationId' => $conversation->id, 'messageId' => $message->id]), ['class' => 'conversation-message__status conversation-message__status--read', 'title' => 'Von mindestens einer Person gelesen', 'data-bs-target' => '#globalModal']) ?>
                        <?php else: ?>
                            <span class="conversation-message__status" title="Veröffentlicht und verfügbar">✓✓</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$message->isDeleted): ?>
                    <div class="conversation-message__actions">
                        <?= Html::a('Reaktion', '#', [
                            'class' => 'conversation-message__reaction-trigger',
                            'data-action-click' => 'ui.modal.load',
                            'data-action-url' => $contentContainer->createUrl('/conversations/conversation/reaction-picker', ['conversationId' => $conversation->id, 'messageId' => $message->id]),
                        ]) ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($reactionSummaries[$message->id])): ?>
                    <div class="conversation-message__reactions" aria-label="Reaktionen">
                        <?php foreach ($reactionSummaries[$message->id] as $reaction): ?>
                            <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/react', ['conversationId' => $conversation->id, 'messageId' => $message->id]), 'post', ['class' => 'conversation-message__reaction-form']) ?>
                                <?= Html::hiddenInput('emoji', $reaction['emoji']) ?>
                                <?= Html::submitButton($reaction['emoji'] . ' ' . $reaction['count'], [
                                    'class' => 'conversation-message__reaction' . ($reaction['mine'] ? ' conversation-message__reaction--mine' : ''),
                                    'title' => $reaction['mine'] ? 'Eigene Reaktion entfernen' : 'Mit dieser Reaktion antworten',
                                ]) ?>
                            <?= Html::endForm() ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!$message->isDeleted && $message->edited_at !== null): ?>
                    <div class="conversation-message__edited">
                        <?= Html::a('bearbeitet', '#', [
                            'title' => 'Änderungsverlauf anzeigen',
                            'data-action-click' => 'ui.modal.load',
                            'data-action-url' => $contentContainer->createUrl('/conversations/conversation/edit-history', ['conversationId' => $conversation->id, 'messageId' => $message->id]),
                        ]) ?>
                    </div>
                <?php endif; ?>
            </article>
            <?php foreach ($subconversationsByOrigin[(int) $message->id] ?? [] as $subconversation): ?>
                <?= $this->render('_subconversation-card', ['subconversation' => $subconversation]) ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>

    <?php if (!$conversation->isClosed): ?>
        <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/message', ['conversationId' => $conversation->id]), 'post', ['class' => 'conversation-composer']) ?>
            <?= Html::textarea('ConversationMessage[message]', '', ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Nachricht schreiben …', 'required' => true]) ?>
            <div class="conversation-composer__controls">
                <div><?= $uploads->button() ?><?= $uploads->progress() ?><?= $uploads->preview() ?></div>
                <div class="conversation-composer__actions">
                    <?php if ($conversation->content->canEdit()): ?>
                        <span class="dropdown conversation-composer__conversation-menu">
                            <?= Html::a('⋯', '#', ['class' => 'conversation-composer__menu-trigger', 'data-bs-toggle' => 'dropdown', 'role' => 'button', 'aria-label' => 'Optionen für Unterhaltung', 'title' => 'Optionen für Unterhaltung']) ?>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><?= Html::a('Unterhaltung beenden …', '#', [
                                    'class' => 'dropdown-item',
                                    'data-action-click' => 'ui.modal.load',
                                    'data-action-url' => $contentContainer->createUrl('/conversations/conversation/close', ['conversationId' => $conversation->id]),
                                ]) ?></li>
                            </ul>
                        </span>
                    <?php endif; ?>
                    <?= Html::submitButton('Senden', ['class' => 'btn btn-primary']) ?>
                </div>
            </div>
        <?= Html::endForm() ?>
    <?php endif; ?>
</section>

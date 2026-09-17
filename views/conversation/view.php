<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\models\ConversationUserSetting;
use humhub\modules\conversations\services\ConversationStateService;
use humhub\modules\conversations\services\ConversationReactionService;
use humhub\modules\conversations\services\ConversationRealtimeService;
use humhub\modules\conversations\models\ConversationConsensusResponse;
use humhub\modules\file\widgets\ShowFiles;
use humhub\modules\file\widgets\Upload;
use humhub\modules\content\widgets\richtext\RichTextField;
use humhub\modules\user\models\Mentioning;
use humhub\modules\user\widgets\Image as UserImage;

ConversationAsset::register($this);
$stateService = new ConversationStateService();
$currentUser = Yii::$app->user->identity;
$uploads = Upload::withName('fileList[]');
$composerMessage = new \humhub\modules\conversations\models\ConversationMessage($contentContainer);
$sendWithCtrlEnter = ConversationUserSetting::sendWithCtrlEnter($currentUser);
$reactionSummaries = (new ConversationReactionService())->summaries($messages, $currentUser);
$realtimeConnection = (new ConversationRealtimeService())->socketConnection($conversation, $currentUser);
$interestTooltip = $isInterested
    ? 'Dieser Chat interessiert dich. Klicken, um laufende Hinweise auszuschalten.'
    : 'Dieser Chat interessiert dich nicht. Klicken, um laufende Hinweise einzuschalten.';
$avatar = static function ($user): string {
    // The core widget owns status visibility and privacy. When HumHub's
    // online-status provider is absent or disabled, it renders a normal avatar.
    return UserImage::widget([
        'user' => $user,
        'width' => 22,
        'height' => 22,
        'showSelfOnlineStatus' => true,
        'htmlOptions' => ['class' => 'conversation-avatar'],
        'imageOptions' => ['class' => 'conversation-avatar__image'],
        'linkOptions' => ['class' => 'conversation-avatar__link'],
    ]);
};
?>
<section class="conversation-view" data-conversation-live-url="<?= Html::encode($contentContainer->createUrl('/conversations/conversation/live-state', ['conversationId' => $conversation->id])) ?>" data-conversation-live-messages-url="<?= Html::encode($contentContainer->createUrl('/conversations/conversation/live-messages', ['conversationId' => $conversation->id])) ?>" data-conversation-typing-url="<?= Html::encode($contentContainer->createUrl('/conversations/conversation/typing', ['conversationId' => $conversation->id])) ?>" data-conversation-typing-state-url="<?= Html::encode($contentContainer->createUrl('/conversations/conversation/typing-state', ['conversationId' => $conversation->id])) ?>" data-conversation-latest-message-id="<?= (int) $latestMessageId ?>"<?php if ($realtimeConnection !== null): ?> data-conversation-realtime-url="<?= Html::encode($realtimeConnection['url']) ?>" data-conversation-realtime-token="<?= Html::encode($realtimeConnection['token']) ?>"<?php endif; ?>>
    <header class="conversation-view__header">
        <div class="conversation-view__heading">
            <?php if ($conversation->parentConversation !== null): ?>
                <?= Html::a('← Zur Hauptkonversation: ' . Html::encode($conversation->parentConversation->title), $conversation->parentConversation->url, ['class' => 'conversation-view__back']) ?>
            <?php else: ?>
                <?= Html::a('← ' . Html::encode($contentContainer->displayName), $contentContainer->createUrl('/conversations/conversation/index'), ['class' => 'conversation-view__back']) ?>
            <?php endif; ?>
            <h1><?= Html::encode($conversation->title) ?></h1>
            <?php if ($conversation->summary): ?><p><?= Html::encode($conversation->summary) ?></p><?php endif; ?>
        </div>
        <div class="conversation-view__actions">
            <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/toggle-interest', ['conversationId' => $conversation->id]), 'post', ['class' => 'conversation-interest-toggle']) ?>
                <?= Html::submitButton(
                    Html::tag('i', '', ['class' => 'fa ' . ($isInterested ? 'fa-bell' : 'fa-bell-slash-o'), 'aria-hidden' => 'true'])
                    . Html::tag('span', $isInterested ? 'Hinweise an' : 'Hinweise aus', ['class' => 'visually-hidden']),
                    ['class' => 'btn btn-default btn-sm' . ($isInterested ? ' is-interested' : ' is-not-interested'), 'title' => $interestTooltip, 'aria-label' => $interestTooltip]
                ) ?>
            <?= Html::endForm() ?>
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
                    <strong><?= $consensus['status'] === 'confirmed_all' ? 'Konsens' : 'Konsent' ?></strong>
                    <?php if ($consensus['proposal']->supersedes_proposal_id !== null): ?>
                        <span class="conversation-consensus__version">Aktuelle Fassung: Alternativvorschlag</span>
                    <?php endif; ?>
                    <?php if ($consensus['status'] === 'confirmed_all'): ?>
                        <p>Konsens bestätigt: Alle Teilnehmenden stimmen zu.</p>
                    <?php elseif ($consensus['status'] === 'confirmed_timeout'): ?>
                        <p>Konsent bestätigt: Nach 14 Tagen gab es keine schwerwiegenden Einwände.</p>
                    <?php elseif ($consensus['status'] === 'objection'): ?>
                        <p><strong>Es liegt ein schwerwiegender Einwand vor.</strong> Ein Alternativvorschlag kann eine neue Konsentrunde starten.</p>
                    <?php else: ?>
                        <p>Konsent in Klärung: <?= $consensus['consentCount'] ?> von <?= count($consensus['participants']) ?> Teilnehmenden haben keinen schwerwiegenden Einwand. Rückmeldungen sind bis <?= Yii::$app->formatter->asDate($consensus['deadline'], 'long') ?> möglich.</p>
                    <?php endif; ?>
                    <?php if ($consensus['canRespond']): ?>
                        <div class="conversation-consensus__actions">
                            <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/consensus', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]), 'post') ?>
                                <?= Html::hiddenInput('decision', ConversationConsensusResponse::DECISION_CONSENT) ?>
                                <?= Html::submitButton(($consensus['responses'][(int) $currentUser->id] ?? null) === ConversationConsensusResponse::DECISION_CONSENT ? 'Konsent gegeben' : 'Konsent geben', ['class' => 'btn btn-success btn-sm']) ?>
                            <?= Html::endForm() ?>
                            <?= Html::a('Schwerwiegenden Einwand einbringen', '#', [
                                'class' => 'btn btn-default btn-sm',
                                'data-action-click' => 'ui.modal.load',
                                'data-action-url' => $contentContainer->createUrl('/conversations/conversation/object', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]),
                            ]) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($consensus['canWithdrawObjection']): ?>
                        <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/consensus', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]), 'post', ['class' => 'conversation-consensus__withdraw']) ?>
                            <?= Html::hiddenInput('decision', ConversationConsensusResponse::DECISION_CONSENT) ?>
                                <?= Html::submitButton('Einwand zurücknehmen', ['class' => 'btn btn-default btn-sm']) ?>
                        <?= Html::endForm() ?>
                    <?php endif; ?>
                    <?php if ($consensus['canProposeAlternative']): ?>
                        <?= Html::a('Alternativvorschlag machen', '#', [
                            'class' => 'conversation-consensus__alternative',
                            'data-action-click' => 'ui.modal.load',
                            'data-action-url' => $contentContainer->createUrl('/conversations/conversation/alternative-proposal', ['conversationId' => $conversation->id, 'proposalId' => $consensus['proposal']->id]),
                        ]) ?>
                    <?php endif; ?>
                    <details class="conversation-consensus__participants">
                        <summary>Rückmeldungen und schwerwiegende Einwände</summary>
                        <ul>
                            <?php foreach ($consensus['participantResponses'] as $participant): ?>
                                <li class="conversation-consensus__participant<?= $participant['decision'] === ConversationConsensusResponse::DECISION_OBJECTION ? ' is-objecting' : '' ?>">
                                    <span><?= Html::encode($participant['user']->displayName) ?></span>
                                    <?php if ($participant['decision'] === ConversationConsensusResponse::DECISION_CONSENT): ?>
                                        <strong>Konsent gegeben</strong>
                                    <?php elseif ($participant['decision'] === ConversationConsensusResponse::DECISION_OBJECTION): ?>
                                        <strong>Schwerwiegender Einwand</strong>
                                        <?php if ($participant['reason']): ?><small><?= Html::encode($participant['reason']) ?></small><?php endif; ?>
                                    <?php else: ?>
                                        <em>Rückmeldung steht aus</em>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                    <?php if (count($consensus['history']) > 1): ?>
                        <details class="conversation-consensus__history">
                            <summary>Frühere Ergebnisfassungen (<?= count($consensus['history']) - 1 ?>)</summary>
                            <?php foreach (array_slice($consensus['history'], 1) as $previous): ?>
                                <article>
                                    <strong><?= Html::encode($previous->createdBy?->displayName ?? 'Nicht verfügbar') ?></strong>
                                    <small>· <?= Yii::$app->formatter->asDatetime($previous->created_at, 'short') ?></small>
                                    <p><?= nl2br(Html::encode($previous->body)) ?></p>
                                    <?php foreach ($previous->responses as $response): ?>
                                        <?php if ($response->decision === ConversationConsensusResponse::DECISION_OBJECTION): ?>
                                            <p class="conversation-consensus__historic-objection"><strong>Schwerwiegender Einwand von <?= Html::encode($response->user?->displayName ?? 'Nicht verfügbar') ?>:</strong> <?= Html::encode($response->reason ?? '') ?></p>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </article>
                            <?php endforeach; ?>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!Yii::$app->user->isGuest): ?>
                <?= Html::a('Schwerwiegenden Einwand einbringen und Chat wieder öffnen', '#', [
                    'class' => 'conversation-outcome__reopen btn btn-danger btn-sm',
                    'data-action-click' => 'ui.modal.load',
                    'data-action-url' => $contentContainer->createUrl('/conversations/conversation/reopen', ['conversationId' => $conversation->id]),
                ]) ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <nav class="conversation-view__navigation" aria-label="Chat-Navigation">
        <button type="button" class="btn btn-default btn-sm" data-conversation-jump="unread">Zu neuen Nachrichten</button>
        <button type="button" class="btn btn-default btn-sm" data-conversation-jump="latest">Zu den neuesten Nachrichten</button>
        <label><span class="visually-hidden">Chat durchsuchen</span><input type="search" class="form-control input-sm" placeholder="Im Chat suchen" data-conversation-search></label>
    </nav>
    <div class="conversation-view__messages" aria-live="polite">
        <?php if ($messages === []): ?>
            <p class="text-body-secondary text-center">Noch keine Nachrichten. Starte den Chat.</p>
        <?php endif; ?>
        <?php $lastDate = null; ?>
        <?php foreach ($messages as $message): ?>
            <?php $messageDate = substr((string) $message->content->created_at, 0, 10); ?>
            <?php if ($messageDate !== $lastDate): ?>
                <div class="conversation-date-divider"><?= Yii::$app->formatter->asDate($message->content->created_at, 'long') ?></div>
                <?php $lastDate = $messageDate; ?>
            <?php endif; ?>
            <?php if ($firstUnreadMessageId !== null && $message->id === $firstUnreadMessageId && $unreadCount > 0): ?>
                <div class="conversation-unread-divider" id="first-unread-message">── <?= $unreadCount ?> neue Nachrichten ──</div>
            <?php endif; ?>
            <?php $isOwn = (int) $message->content->created_by === (int) $currentUser->id; ?>
            <?php $mentionsCurrentUser = Mentioning::find()->where(['object_model' => $message::class, 'object_id' => $message->id, 'user_id' => $currentUser->id])->exists(); ?>
            <article class="conversation-message <?= $isOwn ? 'conversation-message--own' : '' ?> <?= $message->isDeleted ? 'conversation-message--deleted' : '' ?>" id="conversation-message-<?= $message->id ?>"<?= $lastSeenMessageId !== null && (int) $message->id === (int) $lastSeenMessageId ? ' data-conversation-resume' : '' ?>>
                <div class="conversation-message__author">
                    <span class="conversation-avatar"><?= $avatar($message->content->createdBy) ?></span>
                    <span><?= Html::encode($message->content->createdBy->displayName) ?></span>
                </div>
                <div class="conversation-message__bubble <?= !$message->isDeleted && $conversation->content->canEdit() ? 'conversation-message__bubble--has-menu' : '' ?>">
                    <?php if ($message->replyToMessage !== null): ?>
                        <a class="conversation-message__reply-preview" href="#conversation-message-<?= $message->replyToMessage->id ?>" data-conversation-jump-message="conversation-message-<?= $message->replyToMessage->id ?>">
                            <strong><?= Html::encode($message->replyToMessage->content->createdBy->displayName) ?></strong>
                            <span><?= Html::encode(mb_strimwidth(strip_tags((string) $message->replyToMessage->message), 0, 120, '…')) ?></span>
                        </a>
                    <?php endif; ?>
                    <?php if ($message->isDeleted): ?>
                        <em>Diese Nachricht wurde gelöscht.</em>
                    <?php else: ?>
                        <div class="conversation-message__content"><?= RichText::output($message->message, ['record' => $message]) ?></div>
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
                    <?= Html::a('Antworten', '#conversation-composer', [
                        'class' => 'conversation-message__reply-trigger',
                        'data-conversation-reply-id' => $message->id,
                        'data-conversation-reply-author' => $message->content->createdBy->displayName,
                        'data-conversation-reply-excerpt' => mb_strimwidth(strip_tags((string) $message->message), 0, 120, '…'),
                    ]) ?>
                    <?= Html::a('Reaktion', '#', [
                            'class' => 'conversation-message__reaction-trigger',
                            'data-action-click' => 'ui.modal.load',
                            'data-action-url' => $contentContainer->createUrl('/conversations/conversation/reaction-picker', ['conversationId' => $conversation->id, 'messageId' => $message->id]),
                    ]) ?>
                    <?php if ($mentionsCurrentUser): ?><span class="conversation-message__mention" title="Du wurdest erwähnt" aria-label="Du wurdest erwähnt">@</span><?php endif; ?>
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

    <div class="conversation-typing" data-conversation-typing hidden aria-live="polite"></div>

    <?php if (!$conversation->isClosed): ?>
        <?= Html::beginForm($contentContainer->createUrl('/conversations/conversation/message', ['conversationId' => $conversation->id]), 'post', ['class' => 'conversation-composer', 'id' => 'conversation-composer', 'data-conversation-draft-key' => 'conversation-draft-' . (int) $conversation->id, 'data-conversation-send-with-ctrl-enter' => $sendWithCtrlEnter ? 'true' : 'false']) ?>
            <div class="conversation-composer__reply" hidden data-conversation-reply-preview></div>
            <?= Html::hiddenInput('ConversationMessage[reply_to_message_id]', '', ['data-conversation-reply-input' => true]) ?>
            <?= Html::hiddenInput('conversationSubmissionToken', Yii::$app->security->generateRandomString(32), ['data-conversation-submission-token' => true]) ?>
            <?= RichTextField::widget([
                'model' => $composerMessage,
                'attribute' => 'message',
                'preset' => 'markdown',
                'id' => 'conversation-message-editor',
                'backupInterval' => 3,
                'placeholder' => 'Nachricht schreiben …  Mit @ kannst du Menschen erwähnen.',
                'mentioningUrl' => $contentContainer->createUrl('/user/mentioning/space', ['id' => $contentContainer->id]),
            ]) ?>
            <details class="conversation-composer__markdown-help"><summary>Formatierung</summary><span><code>**fett**</code> · <code>*kursiv*</code> · <code>&gt; Zitat</code> · <code>- Liste</code> · <code>1. Liste</code></span></details>
            <div class="conversation-composer__controls">
                <div><?= $uploads->button() ?><?= $uploads->progress() ?><?= $uploads->preview() ?></div>
                <div class="conversation-composer__actions">
                    <?php if ($canEnd): ?>
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
                    <span class="conversation-composer__send-group">
                        <span class="conversation-composer__send">
                            <span class="conversation-composer__send-hint">per <?= $sendWithCtrlEnter ? 'STRG+Enter' : 'Enter' ?> senden</span>
                            <?= Html::submitButton('Senden', ['class' => 'btn btn-primary']) ?>
                        </span>
                        <span class="dropdown conversation-composer__shortcut-menu">
                            <?= Html::a('⌄', '#', ['class' => 'conversation-composer__shortcut-menu-trigger', 'data-bs-toggle' => 'dropdown', 'role' => 'button', 'aria-label' => 'Tastatur-Einstellung', 'title' => 'Tastatur-Einstellung']) ?>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Senden mit</h6></li>
                                <li><?= Html::submitButton('Enter', ['class' => 'dropdown-item' . (!$sendWithCtrlEnter ? ' active' : ''), 'form' => 'conversation-shortcut-enter']) ?></li>
                                <li><?= Html::submitButton('STRG+Enter', ['class' => 'dropdown-item' . ($sendWithCtrlEnter ? ' active' : ''), 'form' => 'conversation-shortcut-ctrl-enter']) ?></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><?= Html::a('Chat-Einstellungen', ['/conversations/settings/index'], ['class' => 'dropdown-item']) ?></li>
                            </ul>
                        </span>
                    </span>
                </div>
            </div>
        <?= Html::endForm() ?>
        <?= Html::beginForm(['/conversations/settings/set-composer-shortcut'], 'post', ['id' => 'conversation-shortcut-enter', 'class' => 'd-none']) ?>
            <?= Html::hiddenInput('sendWithCtrlEnter', '0') ?>
            <?= Html::hiddenInput('returnUrl', Yii::$app->request->url) ?>
        <?= Html::endForm() ?>
        <?= Html::beginForm(['/conversations/settings/set-composer-shortcut'], 'post', ['id' => 'conversation-shortcut-ctrl-enter', 'class' => 'd-none']) ?>
            <?= Html::hiddenInput('sendWithCtrlEnter', '1') ?>
            <?= Html::hiddenInput('returnUrl', Yii::$app->request->url) ?>
        <?= Html::endForm() ?>
    <?php endif; ?>
</section>

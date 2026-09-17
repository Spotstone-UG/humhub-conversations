<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\assets\ConversationAsset;

/** @var array<int, array{space: \humhub\modules\space\models\Space, conversations: \humhub\modules\conversations\models\Conversation[], unreadCounts: array<int, int>, isMuted: bool}> $groups */
ConversationAsset::register($this);
$activeGroups = array_filter($groups, static fn(array $group): bool => !$group['isMuted']);
$mutedGroups = array_filter($groups, static fn(array $group): bool => $group['isMuted']);
?>
<div class="container conversation-global-overview" data-conversation-overview-live-url="<?= Html::encode(\yii\helpers\Url::to(['/conversations/overview/live-state'])) ?>" data-conversation-overview-unread="<?= (int) $unreadTotal ?>" data-conversation-overview-revision="<?= Html::encode($overviewRevision) ?>">
    <div class="panel panel-default conversation-index">
        <div class="panel-heading">
            <strong>Chats</strong>
            <span class="text-body-secondary">Alle Chats aus Spaces, die du lesen darfst.</span>
        </div>
        <div class="panel-body">
            <?php if ($activeGroups === [] && $mutedGroups === []): ?>
                <p class="text-body-secondary mb-0">Für dich sind noch keine Chats sichtbar.</p>
            <?php endif; ?>

            <?php if ($activeGroups === [] && $mutedGroups !== []): ?>
                <div class="conversation-global-overview__quiet">
                    <strong>Alles ruhig.</strong> Deine sichtbaren Chats liegen derzeit in stummen Spaces und lenken dich nicht ab.
                    <button type="button" class="btn btn-link btn-sm" data-conversation-open-muted>Stumme Spaces anzeigen</button>
                </div>
            <?php endif; ?>

            <?php if ($activeGroups !== []): ?>
                <div class="conversation-overview__filters" role="group" aria-label="Chats filtern">
                    <button type="button" class="btn btn-default btn-sm is-active" data-conversation-filter="all">Alle</button>
                    <button type="button" class="btn btn-default btn-sm" data-conversation-filter="unread">Ungelesen</button>
                    <button type="button" class="btn btn-default btn-sm" data-conversation-filter="open">Offen</button>
                    <button type="button" class="btn btn-default btn-sm" data-conversation-filter="closed">Beendet</button>
                    <label><span class="visually-hidden">Chats durchsuchen</span><input class="form-control input-sm" type="search" placeholder="Chats durchsuchen" data-conversation-overview-search></label>
                </div>
            <?php endif; ?>

            <?php foreach ($activeGroups as $group): ?>
                <?= $this->render('_space-group', ['group' => $group, 'currentUser' => $currentUser]) ?>
            <?php endforeach; ?>

            <?php if ($mutedGroups !== []): ?>
                <details class="conversation-global-overview__muted-spaces" data-conversation-muted-spaces>
                    <summary>Stumme Spaces (<?= count($mutedGroups) ?>)</summary>
                    <p>Neue Nachrichten aus diesen Spaces zählen nicht zu deinen ungelesenen Chats.</p>
                    <?php foreach ($mutedGroups as $group): ?>
                        <?= $this->render('_space-group', ['group' => $group, 'currentUser' => $currentUser]) ?>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        </div>
    </div>
</div>

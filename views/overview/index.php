<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\assets\ConversationAsset;

/** @var array<int, array{space: \humhub\modules\space\models\Space, conversations: \humhub\modules\conversations\models\Conversation[], unreadCounts: array<int, int>, isMuted: bool}> $groups */
ConversationAsset::register($this);
$activeGroups = array_filter($groups, static fn(array $group): bool => !$group['isMuted']);
$mutedGroups = array_filter($groups, static fn(array $group): bool => $group['isMuted']);
?>
<div class="container conversation-global-overview">
    <div class="panel panel-default conversation-index">
        <div class="panel-heading">
            <strong>Chats</strong>
            <span class="text-body-secondary">Alle Chats aus Spaces, die du lesen darfst.</span>
        </div>
        <div class="panel-body">
            <?php if ($activeGroups === [] && $mutedGroups === []): ?>
                <p class="text-body-secondary mb-0">Für dich sind noch keine Chats sichtbar.</p>
            <?php endif; ?>

            <?php foreach ($activeGroups as $group): ?>
                <?= $this->render('_space-group', ['group' => $group]) ?>
            <?php endforeach; ?>

            <?php if ($mutedGroups !== []): ?>
                <details class="conversation-global-overview__muted-spaces">
                    <summary>Stumme Spaces (<?= count($mutedGroups) ?>)</summary>
                    <p>Neue Nachrichten aus diesen Spaces zählen nicht zu deinen ungelesenen Chats.</p>
                    <?php foreach ($mutedGroups as $group): ?>
                        <?= $this->render('_space-group', ['group' => $group]) ?>
                    <?php endforeach; ?>
                </details>
            <?php endif; ?>
        </div>
    </div>
</div>

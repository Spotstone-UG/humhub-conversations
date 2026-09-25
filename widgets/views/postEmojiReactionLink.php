<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\post\models\Post $post */
/** @var array<string, array{emoji:string,count:int,mine:bool}> $summary */
$contentContainer = $post->content->container;
?>
<div class="post-emoji-reaction" aria-label="Reaktionen">
    <?php if ($summary !== []): ?>
        <div class="post-emoji-reaction__summary" role="group" aria-label="Vorhandene Reaktionen">
    <?php endif; ?>
    <?php foreach ($summary as $reaction): ?>
        <?= Html::beginForm($contentContainer->createUrl('/conversations/post-reaction/react', ['postId' => $post->id]), 'post', ['class' => 'post-emoji-reaction__form']) ?>
            <?= Html::hiddenInput('emoji', $reaction['emoji']) ?>
            <?= Html::submitButton($reaction['emoji'] . ' ' . $reaction['count'], [
                'class' => 'post-emoji-reaction__chip' . ($reaction['mine'] ? ' post-emoji-reaction__chip--mine' : ''),
                'title' => $reaction['mine'] ? 'Eigene Reaktion entfernen' : 'Mit dieser Reaktion antworten',
                'aria-pressed' => $reaction['mine'] ? 'true' : 'false',
            ]) ?>
        <?= Html::endForm() ?>
    <?php endforeach; ?>
    <?php if ($summary !== []): ?>
        </div>
    <?php endif; ?>
    <?= Html::a('<i class="fa fa-smile-o" aria-hidden="true"></i><span>Reagieren</span>', '#', [
        'class' => 'post-emoji-reaction__trigger',
        'data-action-click' => 'ui.modal.load',
        'data-action-url' => $contentContainer->createUrl('/conversations/post-reaction/picker', ['postId' => $post->id]),
        'aria-label' => 'Emoji-Reaktion auswählen',
    ]) ?>
</div>

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\components\behaviors\PolymorphicRelation;
use humhub\helpers\Html;
use yii\helpers\Url;

/** @var \humhub\modules\content\components\ContentActiveRecord|\humhub\modules\content\components\ContentAddonActiveRecord $content */
/** @var array<string, array{emoji:string,count:int,mine:bool,users:list<string>}> $summary */
/** @var string $returnUrl */
$reactionParams = [
    'contentModel' => PolymorphicRelation::getObjectModel($content),
    'contentId' => $content->getPrimaryKey(),
    'returnUrl' => $returnUrl,
];
$reactionTarget = 'conversation-reaction-' . hash('sha256', $reactionParams['contentModel'] . ':' . $reactionParams['contentId']);
$reactionParams['reactionTarget'] = $reactionTarget;
$reactUrl = Url::to(['/conversations/content-reaction/react'] + $reactionParams);
?>
<span class="likeLinkContainer" id="<?= Html::encode($reactionTarget) ?>" aria-label="Reaktionen">
    <?php if ($summary !== []): ?>
        <span role="group" aria-label="Vorhandene Reaktionen">
    <?php endif; ?>
    <?php foreach ($summary as $reaction): ?>
        <?php $reactionHint = $reaction['mine'] ? 'Klicken, um deine Reaktion zu entfernen.' : 'Klicken, um ebenfalls so zu reagieren.'; ?>
        <?php $reactedBy = "Reagiert von:\n• " . implode("\n• ", $reaction['users']); ?>
        <?= Html::beginForm($reactUrl, 'post', [
            'class' => 'd-inline',
            'data-conversation-reaction-submit' => 'true',
            'data-conversation-reaction-target' => $reactionTarget,
        ]) ?>
            <?= Html::hiddenInput('emoji', $reaction['emoji']) ?>
            <?= Html::submitButton($reaction['emoji'] . ' ' . $reaction['count'], [
                'class' => 'likeAnchor',
                'title' => $reactionHint . "\n\n" . $reactedBy,
                'aria-label' => $reaction['emoji'] . ': ' . $reactionHint . ' ' . str_replace("\n", ' ', $reactedBy),
                'aria-pressed' => $reaction['mine'] ? 'true' : 'false',
            ]) ?>
        <?= Html::endForm() ?>
    <?php endforeach; ?>
    <?php if ($summary !== []): ?>
        </span>
    <?php endif; ?>
    <?= Html::a($summary === [] ? 'Reagieren' : 'Weitere Reaktion', '#', [
        'class' => 'like likeAnchor',
        'data-action-click' => 'ui.modal.load',
        'data-action-url' => Url::to(['/conversations/content-reaction/picker'] + $reactionParams),
        'aria-label' => $summary === [] ? 'Emoji-Reaktion auswählen' : 'Weitere Emoji-Reaktion auswählen',
    ]) ?>
</span>

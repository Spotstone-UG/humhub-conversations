<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;

/** @var \humhub\modules\conversations\models\Conversation $subconversation */
?>
<article class="subconversation-card">
    <span class="subconversation-card__eyebrow">↳ Unterthema</span>
    <h3><?= Html::a(Html::encode($subconversation->title), $subconversation->url) ?></h3>
    <?php if ($subconversation->summary): ?><p><?= Html::encode($subconversation->summary) ?></p><?php endif; ?>
    <footer>
        <span><?= $subconversation->messageCount ?> Nachrichten</span>
        <span>·</span>
        <span class="conversation-status <?= $subconversation->isClosed ? 'conversation-status--closed' : '' ?>"><?= $subconversation->isClosed ? 'Beendet' : 'Offen' ?></span>
        <?= Html::a('Öffnen ›', $subconversation->url, ['class' => 'subconversation-card__open']) ?>
    </footer>
</article>

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\conversations\assets\ConversationAsset;

/** @var array<int, array{slug:string,name:string,icon:string,emojis:array<int, array{emoji:string,name:string}>}> $categories */
/** @var string $submitUrl */
ConversationAsset::register($this);
?>
<div class="modal-dialog modal-dialog-scrollable conversation-reaction-picker-dialog">
    <div class="modal-content conversation-reaction-picker">
        <div class="modal-header"><h4 class="modal-title">Reaktion auswählen</h4></div>
        <div class="modal-body">
            <div class="conversation-reaction-picker__tabs" role="tablist" aria-label="Emoji-Kategorien">
                <?php foreach ($categories as $index => $category): ?>
                    <?= Html::button($category['icon'], ['class' => 'conversation-reaction-picker__tab' . ($index === 0 ? ' is-active' : ''), 'type' => 'button', 'title' => $category['name'], 'aria-label' => $category['name'], 'data-conversation-reaction-category' => $category['slug']]) ?>
                <?php endforeach; ?>
            </div>
            <label class="visually-hidden" for="conversation-reaction-search">Emoji suchen</label>
            <input class="form-control mb-3" id="conversation-reaction-search" type="search" placeholder="Emoji suchen …" data-conversation-reaction-search>
            <p class="text-body-secondary small" data-conversation-reaction-heading><?= Html::encode($categories[0]['name'] ?? 'Emoji') ?></p>
            <?php foreach ($categories as $index => $category): ?>
                <div class="conversation-reaction-picker__category" data-conversation-reaction-category-panel="<?= Html::encode($category['slug']) ?>"<?= $index === 0 ? '' : ' hidden' ?>>
                    <?= Html::beginForm($submitUrl, 'post', ['class' => 'conversation-reaction-picker__grid']) ?>
                        <?php foreach ($category['emojis'] as $emoji): ?>
                            <?= Html::submitButton($emoji['emoji'], ['class' => 'conversation-reaction-picker__emoji', 'name' => 'emoji', 'value' => $emoji['emoji'], 'title' => $emoji['name'], 'aria-label' => $emoji['name'], 'data-conversation-reaction-name' => $emoji['name']]) ?>
                        <?php endforeach; ?>
                    <?= Html::endForm() ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Schließen</button></div>
    </div>
</div>

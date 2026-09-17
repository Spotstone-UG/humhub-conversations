<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\modules\content\widgets\richtext\RichText;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\models\ConversationMessageRevision;

/** @var ConversationMessage $message */
/** @var ConversationMessageRevision[] $revisions */
?>
<div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content conversation-edit-history">
        <div class="modal-header"><h4 class="modal-title">Änderungsverlauf</h4></div>
        <div class="modal-body">
            <?php if ($revisions === []): ?>
                <p class="mb-0">Für diese Nachricht liegt noch kein gespeicherter Änderungsverlauf vor.</p>
            <?php else: ?>
                <?php foreach ($revisions as $revision): ?>
                    <section class="conversation-edit-history__revision">
                        <h5><?= Html::encode($revision->editor->displayName) ?> · <?= Yii::$app->formatter->asDatetime($revision->edited_at, 'short') ?></h5>
                        <div class="conversation-edit-history__before"><span>Vorher</span><?= RichText::output($revision->previous_message, ['record' => $message]) ?></div>
                        <div class="conversation-edit-history__after"><span>Nachher</span><?= RichText::output($revision->revised_message, ['record' => $message]) ?></div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Schließen</button></div>
    </div>
</div>

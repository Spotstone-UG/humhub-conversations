<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
?>
<div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Gelesen von</h4></div>
        <div class="modal-body">
            <?php if ($receipts === []): ?>
                <p class="mb-0">Noch keine freiwilligen Lesebestätigungen.</p>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($receipts as $receipt): ?>
                        <li class="list-group-item d-flex justify-content-between"><span><?= Html::encode($receipt->user->displayName) ?></span><time><?= Yii::$app->formatter->asDatetime($receipt->read_at, 'short') ?></time></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Schließen</button></div>
    </div>
</div>

<?php
// SPDX-License-Identifier: AGPL-3.0-only
use humhub\helpers\Html;
use humhub\modules\content\models\Content;
use humhub\modules\space\models\Space;
use humhub\widgets\form\ActiveForm;
$form = ActiveForm::begin(['action' => $contentContainer->createUrl('/conversations/conversation/visibility', ['conversationId' => $conversation->id])]);
?>
<div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
        <div class="modal-header"><h4 class="modal-title">Für wen ist dieser Chat sichtbar?</h4></div>
        <div class="modal-body">
            <fieldset>
                <legend class="visually-hidden">Sichtbarkeit wählen</legend>
                <?php foreach ($labels as $value => $label): ?>
                    <div class="radio mb-3"><label>
                        <?= Html::radio('visibility', (new \humhub\modules\conversations\services\ConversationVisibilityService())->mode($conversation) === $value, [
                            'value' => (string) $value, 'disabled' => $value > 0 && !$canShare,
                        ]) ?> <?= Html::encode($label) ?>
                    </label></div>
                <?php endforeach; ?>
            </fieldset>
            <p>Die Auswahl gilt für Personen außerhalb des Spaces. Mitglieder behalten ihre bisherigen Schreibrechte. Bei „Öffentlich lesbar“ können Außenstehende mitlesen und abonnieren, bei „Öffentlich les- und schreibbar“ auch Nachrichten und Reaktionen beitragen.</p>
            <p>Die Einstellung gilt für bisherige und künftige Nachrichten und ihre Anhänge. Unterthemen haben eine eigene Sichtbarkeit. Benachrichtigungen gibt es erst nach einem Chat-Abonnement oder einer @Erwähnung.</p>
            <?php if ((int) $contentContainer->visibility === Space::VISIBILITY_ALL): ?>
                <p class="help-block">Öffentliche Chats können in diesem Space auch ohne Anmeldung lesbar sein, wenn die Community Gastzugriff erlaubt. Schreiben erfordert immer eine Anmeldung.</p>
            <?php elseif (!$canShare): ?>
                <p class="help-block">Dieser Space ist privat oder dir fehlt das Recht, Inhalte außerhalb des Spaces freizugeben. „Alle“ ist deshalb nicht auswählbar.</p>
            <?php else: ?>
                <p class="help-block">„Alle“ bedeutet hier: alle angemeldeten Community-Mitglieder, auch ohne Mitgliedschaft im Kreis.</p>
            <?php endif; ?>
            <p class="help-block">HumHubs Administrationsrechte bleiben bestehen.</p>
        </div>
        <div class="modal-footer">
            <?= Html::button('Abbrechen', ['class' => 'btn btn-default', 'data-bs-dismiss' => 'modal']) ?>
            <?= Html::submitButton('Sichtbarkeit speichern', ['class' => 'btn btn-primary']) ?>
        </div>
    </div>
</div>
<?php ActiveForm::end(); ?>

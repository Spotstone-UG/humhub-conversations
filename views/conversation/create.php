<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\helpers\Html;
use humhub\widgets\form\ActiveForm;

/** @var \humhub\modules\conversations\models\Conversation $conversation */
$form = ActiveForm::begin();
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Neuer Chat</strong></div>
    <div class="panel-body">
        <?= $form->field($conversation, 'title')->textInput(['maxlength' => 255, 'autofocus' => true])->label('Thema') ?>
        <?= $form->field($conversation, 'summary')->textarea(['rows' => 3, 'maxlength' => 1000])->label('Kurzfassung') ?>
        <p class="help-block">Die Kurzfassung erscheint im Stream mit höchstens zwei Zeilen. KI-Zusammenfassungen sind bewusst nicht Teil dieser Version.</p>
        <?= Html::submitButton('Chat starten', ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Abbrechen', $contentContainer->createUrl('/conversations/conversation/index'), ['class' => 'btn btn-default']) ?>
    </div>
</div>
<?php ActiveForm::end(); ?>

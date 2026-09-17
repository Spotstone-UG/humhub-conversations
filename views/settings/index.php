<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;

$form = ActiveForm::begin();
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Chats</strong></div>
    <div class="panel-body">
        <?= $form->field($model, 'readReceiptsEnabled')->checkbox(['label' => 'Lesebestätigungen senden']) ?>
        <p class="help-block">Wenn diese Option deaktiviert ist, erscheinst du nicht unter „Gelesen von“. Deine persönliche Anzeige „neu“ funktioniert weiterhin. Solange sie deaktiviert ist, siehst du selbst keine personenbezogenen Lesebestätigungen.</p>
        <hr>
        <?= $form->field($model, 'sendWithCtrlEnter')->checkbox(['label' => 'STRG+Enter zum Senden verwenden']) ?>
        <p class="help-block">Standardmäßig sendet Enter; STRG+Enter erzeugt eine neue Zeile. Mit dieser Option sendet STRG+Enter, während Enter eine neue Zeile erzeugt.</p>
        <?= Html::submitButton('Speichern', ['class' => 'btn btn-primary']) ?>
    </div>
</div>
<?php ActiveForm::end(); ?>

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;

$form = ActiveForm::begin();
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Conversations</strong></div>
    <div class="panel-body">
        <?= $form->field($model, 'readReceiptsEnabled')->checkbox(['label' => 'Lesebestätigungen senden']) ?>
        <p class="help-block">Wenn diese Option deaktiviert ist, erscheinst du nicht unter „Gelesen von“. Deine persönliche Anzeige „neu“ funktioniert weiterhin. Solange sie deaktiviert ist, siehst du selbst keine personenbezogenen Lesebestätigungen.</p>
        <?= Html::submitButton('Speichern', ['class' => 'btn btn-primary']) ?>
    </div>
</div>
<?php ActiveForm::end(); ?>


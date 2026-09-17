<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\modules\content\widgets\WallCreateContentFormFooter;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\widgets\ConversationForm;
use humhub\widgets\form\ActiveForm;

/** @var Conversation $conversation */
/** @var ConversationForm $wallCreateContentForm */
/** @var ActiveForm $form */
?>
<?= $form->field($conversation, 'title')->textInput([
    'placeholder' => Yii::t('ConversationsModule.base', 'Worum geht es?'),
    'maxlength' => true,
])->label(false) ?>

<?= $form->field($conversation, 'summary')->textarea([
    'placeholder' => Yii::t('ConversationsModule.base', 'Kurze Einordnung für den Stream (maximal zwei Zeilen).'),
    'maxlength' => true,
    'rows' => 2,
])->label(false) ?>

<?= WallCreateContentFormFooter::widget([
    'contentContainer' => $conversation->content->container,
    'wallCreateContentForm' => $wallCreateContentForm,
    'submitButtonText' => Yii::t('ConversationsModule.base', 'Conversation starten'),
]) ?>

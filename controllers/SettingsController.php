<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\modules\conversations\models\ConversationUserSetting;
use humhub\modules\conversations\models\forms\ReadReceiptSettingsForm;
use humhub\modules\user\components\BaseAccountController;
use humhub\modules\user\widgets\AccountMenu;
use Yii;

final class SettingsController extends BaseAccountController
{
    public function actionIndex()
    {
        AccountMenu::markAsActive('account-settings-conversations');
        $setting = ConversationUserSetting::findOne(['user_id' => $this->getUser()->id]);
        $form = new ReadReceiptSettingsForm([
            'readReceiptsEnabled' => $setting === null || (bool) $setting->read_receipts_enabled,
        ]);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $setting ??= new ConversationUserSetting(['user_id' => $this->getUser()->id]);
            $setting->read_receipts_enabled = (int) $form->readReceiptsEnabled;
            $setting->save(false);
            Yii::$app->session->setFlash('success', 'Deine Einstellung wurde gespeichert.');
            return $this->refresh();
        }

        return $this->render('index', ['model' => $form]);
    }
}


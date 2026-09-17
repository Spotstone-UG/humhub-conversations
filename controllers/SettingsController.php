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
            'sendWithCtrlEnter' => $setting !== null && (bool) $setting->send_with_ctrl_enter,
        ]);

        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            $setting ??= new ConversationUserSetting(['user_id' => $this->getUser()->id]);
            $setting->read_receipts_enabled = (int) $form->readReceiptsEnabled;
            $setting->send_with_ctrl_enter = (int) $form->sendWithCtrlEnter;
            $setting->save(false);
            Yii::$app->session->setFlash('success', 'Deine Einstellung wurde gespeichert.');
            return $this->refresh();
        }

        return $this->render('index', ['model' => $form]);
    }

    public function actionSetComposerShortcut()
    {
        $sendWithCtrlEnter = Yii::$app->request->post('sendWithCtrlEnter') === '1';
        $setting = ConversationUserSetting::findOne(['user_id' => $this->getUser()->id])
            ?? new ConversationUserSetting(['user_id' => $this->getUser()->id]);
        $setting->send_with_ctrl_enter = (int) $sendWithCtrlEnter;
        $setting->save(false);

        Yii::$app->session->setFlash('success', 'Deine Tastatur-Einstellung wurde gespeichert.');
        $returnUrl = (string) Yii::$app->request->post('returnUrl', '');
        if (str_starts_with($returnUrl, '/') && !str_starts_with($returnUrl, '//')) {
            return $this->redirect($returnUrl);
        }

        return $this->redirect(['/conversations/settings/index']);
    }
}

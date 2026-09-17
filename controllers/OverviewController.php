<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\components\behaviors\AccessControl;
use humhub\components\Controller;
use humhub\modules\conversations\services\ConversationOverviewService;
use Yii;

/** Displays every chat the current member may read, grouped by Space. */
final class OverviewController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['login']],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $groups = (new ConversationOverviewService())->groupedBySpace(Yii::$app->user->identity);

        return $this->render('index', ['groups' => $groups]);
    }

    public function actionToggleMute(int $spaceId)
    {
        $this->forcePostRequest();
        $service = new ConversationOverviewService();
        $groups = $service->groupedBySpace(Yii::$app->user->identity);
        if (!isset($groups[$spaceId])) {
            $this->forbidden();
        }

        $isMuted = $service->toggleMutedSpace(Yii::$app->user->identity, $groups[$spaceId]['space']);
        Yii::$app->session->setFlash('success', $isMuted ? 'Der Space ist stummgeschaltet.' : 'Der Space ist nicht mehr stummgeschaltet.');

        return $this->redirect(['/conversations/overview/index']);
    }
}

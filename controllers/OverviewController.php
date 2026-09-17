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
}

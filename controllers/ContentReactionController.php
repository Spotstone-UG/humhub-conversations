<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\modules\content\components\ContentAddonController;
use humhub\modules\conversations\services\ContentEmojiReactionService;
use humhub\modules\conversations\services\EmojiPaletteService;
use humhub\modules\conversations\widgets\ContentEmojiReactionLink;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/** Emoji reactions for every likeable ContentActiveRecord in a Space. */
final class ContentReactionController extends ContentAddonController
{
    public function actionPicker(): string
    {
        $content = $this->content();

        return $this->renderAjax('picker', [
            'categories' => (new EmojiPaletteService())->categories(),
            'submitUrl' => $this->reactionUrl(),
            'returnUrl' => $this->returnUrl(),
        ]);
    }

    public function actionReact()
    {
        $this->forcePostRequest();
        $content = $this->content();
        $emoji = (string) Yii::$app->request->post('emoji');
        if (!(new EmojiPaletteService())->contains($emoji)) {
            throw new BadRequestHttpException('Unbekannte Emoji-Reaktion.');
        }

        (new ContentEmojiReactionService())->toggle($content, Yii::$app->user->identity, $emoji);
        return $this->redirect($this->returnUrl());
    }

    private function content(): \humhub\modules\content\components\ContentActiveRecord
    {
        if (!$this->parentContent instanceof \humhub\modules\content\components\ContentActiveRecord
            || !ContentEmojiReactionLink::supports($this->parentContent)) {
            throw new ForbiddenHttpException();
        }

        return $this->parentContent;
    }

    private function reactionUrl(): string
    {
        return \yii\helpers\Url::to(['/conversations/content-reaction/react',
            'contentModel' => $this->contentModel,
            'contentId' => $this->contentId,
            'returnUrl' => $this->returnUrl(),
        ]);
    }

    private function returnUrl(): string
    {
        $url = (string) (Yii::$app->request->post('returnUrl') ?? Yii::$app->request->get('returnUrl') ?? '');
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url;
        }

        return $this->parentContent->content->container->createUrl('/space/space/home');
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\modules\content\components\ContentAddonController;
use humhub\modules\content\components\ContentAddonActiveRecord;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\conversations\services\ContentEmojiReactionService;
use humhub\modules\conversations\services\EmojiPaletteService;
use humhub\modules\conversations\widgets\ContentEmojiReactionLink;
use Yii;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/** Emoji reactions for every likeable ContentActiveRecord in a Space. */
final class ContentReactionController extends ContentAddonController
{
    /** ContentAddonController performs the content-specific read check itself. */
    protected $access = null;

    public function actionPicker(): string
    {
        $content = $this->content();

        return $this->renderAjax('/post-reaction/picker', [
            'categories' => (new EmojiPaletteService())->categories(),
            'submitUrl' => $this->reactionUrl(),
            'returnUrl' => $this->returnUrl(),
            'reactionTarget' => Yii::$app->request->get('reactionTarget'),
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
        if (Yii::$app->request->isAjax) {
            return $this->asJson([
                'html' => ContentEmojiReactionLink::widget([
                    'object' => $content,
                    'returnUrl' => $this->returnUrl(),
                ]),
            ]);
        }

        return $this->redirect($this->returnUrl());
    }

    private function content(): ContentActiveRecord|ContentAddonActiveRecord
    {
        $content = $this->contentAddon ?? $this->parentContent;
        if (!($content instanceof ContentActiveRecord || $content instanceof ContentAddonActiveRecord)
            || !ContentEmojiReactionLink::supports($content)) {
            throw new ForbiddenHttpException();
        }

        return $content;
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
            $query = (string) parse_url($url, PHP_URL_QUERY);
            parse_str($query, $parameters);
            $streamRoutes = [
                'dashboard/dashboard/stream' => 'dashboard/dashboard',
                'dashboard/dashboard/activity-stream' => 'dashboard/dashboard',
                'user/profile/stream' => 'user/profile/home',
                'space/space/stream' => 'space/space/home',
            ];
            $route = $parameters['r'] ?? null;
            if (is_string($route) && isset($streamRoutes[$route])) {
                unset($parameters['r'], $parameters['StreamQuery']);
                return Url::to(array_merge(['/' . $streamRoutes[$route]], $parameters));
            }

            return $url;
        }

        return $this->content()->content->container->createUrl('/space/space/home');
    }
}

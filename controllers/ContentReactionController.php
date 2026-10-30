<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\components\Controller;
use humhub\components\access\ControllerAccess;
use humhub\components\behaviors\PolymorphicRelation;
use humhub\helpers\DataTypeHelper;
use yii\web\NotFoundHttpException;
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
final class ContentReactionController extends Controller
{
    protected $access = null;
    private ContentActiveRecord|ContentAddonActiveRecord|null $reactionContent = null;
    public string $contentModel = '';
    public int $contentId = 0;

    protected function getAccessRules()
    {
        return [[ControllerAccess::RULE_LOGGED_IN_ONLY]];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }
        $request = Yii::$app->request;
        $model = $request->get('contentModel', $request->post('contentModel'));
        $id = $request->get('contentId', $request->post('contentId'));
        if (!is_string($model) || !is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
            throw new BadRequestHttpException();
        }
        // Validate the class before calling any user-selected model method.
        $class = DataTypeHelper::matchClassType($model, [ContentActiveRecord::class, ContentAddonActiveRecord::class]);
        if ($class === null) {
            throw new BadRequestHttpException();
        }
        $target = $class::findOne(['id' => (int) $id]);
        if ($target === null) {
            throw new NotFoundHttpException();
        }
        if (!ContentEmojiReactionLink::supports($target)) {
            throw new ForbiddenHttpException();
        }
        $this->reactionContent = $target;
        $this->contentModel = PolymorphicRelation::getObjectModel($target);
        $this->contentId = (int) $target->getPrimaryKey();
        return true;
    }

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
        $content = $this->reactionContent;
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

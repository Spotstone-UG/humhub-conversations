<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\conversations\services\EmojiPaletteService;
use humhub\modules\conversations\services\PostEmojiReactionService;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\NotFoundHttpException;

final class PostReactionController extends ContentContainerController
{
    public $validContentContainerClasses = [Space::class];

    public function actionPicker(int $postId): string
    {
        $post = $this->findPost($postId);

        return $this->renderAjax('picker', [
            'categories' => (new EmojiPaletteService())->categories(),
            'submitUrl' => $this->contentContainer->createUrl('/conversations/post-reaction/react', ['postId' => $post->id]),
        ]);
    }

    public function actionReact(int $postId)
    {
        $this->forcePostRequest();
        $post = $this->findPost($postId);
        $emoji = (string) Yii::$app->request->post('emoji');

        if (!(new EmojiPaletteService())->contains($emoji)) {
            throw new \yii\web\BadRequestHttpException('Unbekannte Emoji-Reaktion.');
        }

        (new PostEmojiReactionService())->toggle($post, Yii::$app->user->identity, $emoji);

        return $this->redirect($this->contentContainer->createUrl('/space/space/home'));
    }

    private function findPost(int $id): Post
    {
        $post = Post::find()
            ->contentContainer($this->contentContainer)
            ->readable()
            ->where(['post.id' => $id])
            ->one();
        if ($post === null) {
            throw new NotFoundHttpException();
        }

        return $post;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\services\PostEmojiReactionService;
use humhub\modules\post\models\Post;
use humhub\components\Widget;
use Yii;

/** Adds the full emoji palette to every readable, standard HumHub post. */
final class PostEmojiReactionLink extends Widget
{
    public $object;

    public function run(): string
    {
        if (!$this->object instanceof Post || Yii::$app->user->isGuest || !$this->object->content->canView()) {
            return '';
        }

        ConversationAsset::register($this->view);

        return $this->render('postEmojiReactionLink', [
            'post' => $this->object,
            'summary' => (new PostEmojiReactionService())->summary($this->object, Yii::$app->user->identity),
        ]);
    }
}

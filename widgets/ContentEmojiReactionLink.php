<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\components\Widget;
use humhub\modules\content\components\ContentAddonActiveRecord;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\services\ContentEmojiReactionService;
use humhub\modules\like\Module as LikeModule;
use Yii;

/** Adds the full emoji palette to every visible, likeable content item. */
final class ContentEmojiReactionLink extends Widget
{
    public $object;

    /** The page to preserve after an asynchronous reaction update. */
    public ?string $returnUrl = null;

    public static function supports(mixed $object): bool
    {
        if (!($object instanceof ContentActiveRecord || $object instanceof ContentAddonActiveRecord)
            || Yii::$app->user->isGuest
            || !$object->content->canView()) {
            return false;
        }

        $likeModule = Yii::$app->getModule('like');
        return $likeModule instanceof LikeModule && $likeModule->canLike($object);
    }

    public function run(): string
    {
        if (!self::supports($this->object)) {
            return '';
        }

        ConversationAsset::register($this->view);

        return $this->render('contentEmojiReactionLink', [
            'content' => $this->object,
            'summary' => (new ContentEmojiReactionService())->summary($this->object, Yii::$app->user->identity),
            'returnUrl' => $this->returnUrl ?? Yii::$app->request->url,
        ]);
    }
}

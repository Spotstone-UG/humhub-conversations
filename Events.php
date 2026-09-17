<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations;

use humhub\modules\ui\menu\MenuLink;
use humhub\modules\content\widgets\WallEntryLinks;
use humhub\modules\like\widgets\LikeLink;
use humhub\modules\post\models\Post;
use humhub\modules\conversations\widgets\PostEmojiReactionLink;
use humhub\helpers\ControllerHelper;
use Yii;

final class Events
{
    public static function onWallEntryLinksInit($event): void
    {
        if (!$event->sender->object instanceof Post) {
            return;
        }

        $event->sender->addWidget(PostEmojiReactionLink::class, ['object' => $event->sender->object], ['sortOrder' => 30]);
    }

    /** Replaces the native binary Like control on posts with emoji reactions. */
    public static function onWallEntryLinksRun($event): void
    {
        if (!$event->sender->object instanceof Post) {
            return;
        }

        $event->sender->removeWidget(LikeLink::class);
    }

    public static function onAccountMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }

        $event->sender->addEntry(new MenuLink([
            'id' => 'account-settings-conversations',
            'label' => 'Conversations',
            'icon' => 'comments-o',
            'url' => ['/conversations/settings/index'],
            'sortOrder' => 112,
            'isActive' => ControllerHelper::isActivePath('conversations', 'settings'),
        ]));
    }
}

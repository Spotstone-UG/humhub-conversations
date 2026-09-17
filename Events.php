<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations;

use humhub\modules\ui\menu\MenuLink;
use humhub\modules\content\widgets\WallEntryLinks;
use humhub\modules\like\widgets\LikeLink;
use humhub\modules\post\models\Post;
use humhub\modules\conversations\widgets\PostEmojiReactionLink;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\services\ConversationOverviewService;
use humhub\modules\space\widgets\Menu as SpaceMenu;
use humhub\widgets\TopMenu;
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
            'label' => 'Chats',
            'icon' => 'comments-o',
            'url' => ['/conversations/settings/index'],
            'sortOrder' => 112,
            'isActive' => ControllerHelper::isActivePath('conversations', 'settings'),
        ]));
    }

    /** Keeps Chats reachable from every enabled Space without a top tab. */
    public static function onSpaceMenuInit($event): void
    {
        /** @var SpaceMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'id' => 'space-conversations',
            'label' => 'Chats',
            'icon' => 'comments-o',
            'url' => $menu->space->createUrl('/conversations/conversation/index'),
            'sortOrder' => 110,
            'isActive' => ControllerHelper::isActivePath('conversations', 'conversation'),
        ]));
    }

    /** Adds the personal, cross-Space chat overview to HumHub's main navigation. */
    public static function onTopMenuInit($event): void
    {
        if (Yii::$app->user->isGuest) {
            return;
        }

        ConversationAsset::register(Yii::$app->view);
        $unreadCount = (new ConversationOverviewService())->unreadTotal(Yii::$app->user->identity);
        $badge = $unreadCount > 0
            ? '<span class="conversation-top-menu__badge" aria-label="' . $unreadCount . ' ungelesene Nachrichten">' . ($unreadCount > 99 ? '99+' : $unreadCount) . '</span>'
            : '';

        /** @var TopMenu $menu */
        $menu = $event->sender;
        $menu->addEntry(new MenuLink([
            'id' => 'global-chats',
            'label' => 'Chats' . $badge,
            'icon' => 'comments-o',
            'url' => ['/conversations/overview/index'],
            'sortOrder' => 110,
            'isActive' => ControllerHelper::isActivePath('conversations', 'overview'),
        ]));
    }
}

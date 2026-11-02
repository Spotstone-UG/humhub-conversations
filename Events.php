<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations;

use humhub\modules\ui\menu\MenuLink;
use humhub\modules\like\widgets\LikeLink;
use humhub\modules\conversations\widgets\ContentEmojiReactionLink;
use humhub\modules\conversations\assets\ConversationAsset;
use humhub\modules\conversations\services\ConversationOverviewService;
use humhub\modules\space\widgets\Menu as SpaceMenu;
use humhub\widgets\TopMenu;
use humhub\helpers\ControllerHelper;
use Yii;

final class Events
{
    /** Synchronize message and attachment access, including native HumHub controls. */
    public static function onContentVisibilityChanged($event): void
    {
        if (!array_key_exists('visibility', $event->changedAttributes)) { return; }
        $content = $event->sender;
        $conversation = $content->getModel();
        if (!$conversation instanceof \humhub\modules\conversations\models\Conversation) { return; }
        try {
            (new \humhub\modules\conversations\services\ConversationVisibilityService())
                ->synchronizeMessages($conversation, (int) $content->visibility);
        } catch (\Throwable $exception) {
            \humhub\modules\content\models\Content::updateAll(
                ['visibility' => $event->changedAttributes['visibility']], ['id' => $content->id]);
            $content->visibility = $event->changedAttributes['visibility'];
            throw $exception;
        }
        if (Yii::$app->has('user') && !Yii::$app->user->isGuest) {
            (new \humhub\modules\conversations\services\ConversationNotifier())
                ->notifyChanged($conversation, Yii::$app->user->identity);
        }
    }

    /** Replaces every native LikeLink with the same accessible emoji reaction bar. */
    public static function onLikeLinkAfterRun($event): void
    {
        if (!$event->sender instanceof LikeLink
            || !ContentEmojiReactionLink::supports($event->sender->object)) {
            return;
        }

        $event->result = ContentEmojiReactionLink::widget(['object' => $event->sender->object]);
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

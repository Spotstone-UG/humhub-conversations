<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations;

use humhub\helpers\ControllerHelper;
use humhub\modules\ui\menu\MenuLink;
use Yii;

final class Events
{
    public static function onSpaceMenuInit($event): void
    {
        $space = $event->sender->space;
        if (!$space->moduleManager->isEnabled('conversations')) {
            return;
        }

        $event->sender->addEntry(new MenuLink([
            'id' => 'conversations',
            'label' => 'Conversations',
            'icon' => 'comments-o',
            'url' => $space->createUrl('/conversations/conversation/index'),
            'sortOrder' => 220,
            'isActive' => ControllerHelper::isActivePath('conversations', 'conversation'),
        ]));
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

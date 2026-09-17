<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations;

use humhub\modules\ui\menu\MenuLink;
use humhub\helpers\ControllerHelper;
use Yii;

final class Events
{
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

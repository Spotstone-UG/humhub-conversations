<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\modules\conversations\Events;
use humhub\modules\space\widgets\Menu as SpaceMenu;
use humhub\modules\user\widgets\AccountMenu;

return [
    'id' => 'conversations',
    'class' => \humhub\modules\conversations\Module::class,
    'namespace' => 'humhub\modules\conversations',
    'events' => [
        ['class' => SpaceMenu::class, 'event' => SpaceMenu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
    ],
];

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\modules\conversations\Events;
use humhub\modules\user\widgets\AccountMenu;
use humhub\modules\content\widgets\WallEntryLinks;
use humhub\modules\space\widgets\Menu as SpaceMenu;

return [
    'id' => 'conversations',
    'class' => \humhub\modules\conversations\Module::class,
    'namespace' => 'humhub\modules\conversations',
    'events' => [
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
        ['class' => SpaceMenu::class, 'event' => SpaceMenu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => WallEntryLinks::class, 'event' => WallEntryLinks::EVENT_INIT, 'callback' => [Events::class, 'onWallEntryLinksInit']],
        ['class' => WallEntryLinks::class, 'event' => WallEntryLinks::EVENT_RUN, 'callback' => [Events::class, 'onWallEntryLinksRun']],
    ],
];

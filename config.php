<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\modules\conversations\Events;
use humhub\modules\user\widgets\AccountMenu;
use humhub\modules\like\widgets\LikeLink;
use humhub\modules\space\widgets\Menu as SpaceMenu;
use humhub\widgets\TopMenu;

return [
    'id' => 'conversations',
    'class' => \humhub\modules\conversations\Module::class,
    'namespace' => 'humhub\modules\conversations',
    'events' => [
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
        ['class' => TopMenu::class, 'event' => TopMenu::EVENT_INIT, 'callback' => [Events::class, 'onTopMenuInit']],
        ['class' => SpaceMenu::class, 'event' => SpaceMenu::EVENT_INIT, 'callback' => [Events::class, 'onSpaceMenuInit']],
        ['class' => LikeLink::class, 'event' => LikeLink::EVENT_AFTER_RUN, 'callback' => [Events::class, 'onLikeLinkAfterRun']],
    ],
];

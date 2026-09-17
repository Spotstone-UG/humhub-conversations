<?php
// SPDX-License-Identifier: AGPL-3.0-only

use humhub\modules\conversations\Events;
use humhub\modules\user\widgets\AccountMenu;

return [
    'id' => 'conversations',
    'class' => \humhub\modules\conversations\Module::class,
    'namespace' => 'humhub\modules\conversations',
    'events' => [
        ['class' => AccountMenu::class, 'event' => AccountMenu::EVENT_INIT, 'callback' => [Events::class, 'onAccountMenuInit']],
    ],
];

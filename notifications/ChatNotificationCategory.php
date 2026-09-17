<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\notifications;

use humhub\modules\notification\components\NotificationCategory;

/** Uses HumHub targets, so installed push providers decide delivery themselves. */
final class ChatNotificationCategory extends NotificationCategory
{
    public $id = 'conversations-chat';

    public function getTitle(): string
    {
        return 'Chats';
    }

    public function getDescription(): string
    {
        return 'Hinweise zu neuen Chats und zu Chats, die dich interessieren.';
    }

    public function getDefaultSetting($target): bool
    {
        // The Push Notifications (Firebase) module receives the same native
        // notification when the person enabled its own target preference.
        return true;
    }
}

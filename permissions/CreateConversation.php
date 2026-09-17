<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\permissions;

use humhub\libs\BasePermission;
use humhub\modules\space\models\Space;
use Yii;

final class CreateConversation extends BasePermission
{
    public $defaultAllowedGroups = [
        Space::USERGROUP_OWNER,
        Space::USERGROUP_ADMIN,
        Space::USERGROUP_MODERATOR,
        Space::USERGROUP_MEMBER,
    ];

    protected $fixedGroups = [Space::USERGROUP_GUEST];
    protected $moduleId = 'conversations';

    public function getTitle(): string
    {
        return Yii::t('ConversationsModule.base', 'Create conversations');
    }

    public function getDescription(): string
    {
        return Yii::t('ConversationsModule.base', 'Allows members to start and contribute to conversations.');
    }
}


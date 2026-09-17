<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\components\ContentContainerModule;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\permissions\CreateConversation;
use humhub\modules\space\models\Space;

final class Module extends ContentContainerModule
{
    public function getContentContainerTypes(): array
    {
        return [Space::class];
    }

    public function getContentContainerName(ContentContainerActiveRecord $container): string
    {
        return 'Chats';
    }

    public function getContentContainerDescription(ContentContainerActiveRecord $container): string
    {
        return 'Kompakte Chat-Karten im Stream mit einer eigenen Gesprächsansicht.';
    }

    /**
     * Adds Conversation alongside HumHub's standard Beitrag composer.
     */
    public function getContentClasses(?ContentContainerActiveRecord $contentContainer = null): array
    {
        return [Conversation::class];
    }

    public function getContainerPermissions($contentContainer = null): array
    {
        return [new CreateConversation()];
    }

    /** Conversation records deliberately survive disabling the Space module. */
    public function disableContentContainer(ContentContainerActiveRecord $container): void
    {
    }
}

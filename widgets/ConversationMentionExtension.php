<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\components\ActiveRecord;
use humhub\modules\content\widgets\richtext\extensions\mentioning\MentioningExtension;
use humhub\modules\conversations\services\ConversationMentionService;

final class ConversationMentionExtension extends MentioningExtension
{
    public function onPostProcess(string $text, ActiveRecord $record, ?string $attribute, array &$result): string
    {
        $result['mention'] = $result['mentioning'] = (new ConversationMentionService())->process($record);
        return $text;
    }
}

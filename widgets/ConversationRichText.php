<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\widgets;

use humhub\modules\content\widgets\richtext\ProsemirrorRichText;
use humhub\modules\content\widgets\richtext\extensions\mentioning\MentioningExtension;

/** Keep file/oembed processing, replacing only chat mention side effects. */
final class ConversationRichText extends ProsemirrorRichText
{
    public static function getExtensions()
    {
        return array_map(static fn($extension) => $extension instanceof MentioningExtension
            ? new ConversationMentionExtension() : $extension, parent::getExtensions());
    }
}

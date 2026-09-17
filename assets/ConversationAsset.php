<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\assets;

use yii\web\AssetBundle;

final class ConversationAsset extends AssetBundle
{
    public $sourcePath = '@conversations/resources';
    public $css = ['conversations.css'];
    public $js = ['conversations.js'];
    public $depends = ['humhub\\assets\\AppAsset'];
}


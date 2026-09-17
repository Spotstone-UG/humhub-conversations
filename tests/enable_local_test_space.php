<?php
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

use humhub\components\console\Application;
use humhub\modules\space\models\Space;
use humhub\services\BootstrapService;

$root = rtrim((string) getenv('HUMHUB_ROOT'), '/');
if ($root === '' || !str_contains($root, '/.local/share/humhub-test/app')) {
    throw new RuntimeException('This helper only permits the local humhub-test instance.');
}

$guid = $argv[1] ?? '';
if ($guid === '') {
    throw new InvalidArgumentException('Pass the target Space GUID as the first argument.');
}

$protected = $root . '/protected';
$loader = require $protected . '/vendor/autoload.php';
Dotenv\Dotenv::createMutable($root, '.env')->safeLoad();
$loader->addClassMap(['humhub\\services\\BootstrapService' => $protected . '/humhub/services/BootstrapService.php']);
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');
require $protected . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@humhub', $protected . '/humhub');
new Application((new BootstrapService())->getConfig('console'));

$space = Space::findOne(['guid' => $guid]);
if (!$space instanceof Space) {
    throw new RuntimeException('The requested test Space does not exist.');
}

if (!$space->moduleManager->isEnabled('conversations') && !$space->moduleManager->enable('conversations')) {
    throw new RuntimeException('Conversations could not be enabled in the requested test Space.');
}

echo "Conversations enabled for {$space->name}.\n";


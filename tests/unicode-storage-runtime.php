<?php
// SPDX-License-Identifier: AGPL-3.0-only

/** Test installed column definitions using connection-local temporary tables only. */
$root = rtrim((string) ($argv[1] ?? ''), '/');
if (!is_file($root . '/protected/vendor/autoload.php')) {
    throw new RuntimeException('Pass the path to a HumHub installation.');
}
$protected = $root . '/protected';
$loader = require $protected . '/vendor/autoload.php';
require $protected . '/vendor/yiisoft/yii2/Yii.php';
Dotenv\Dotenv::createMutable($root, '.env')->safeLoad();
$loader->addClassMap(['humhub\\services\\BootstrapService' => $protected . '/humhub/services/BootstrapService.php']);
$config = (new humhub\services\BootstrapService())->getConfig('console');
$db = Yii::createObject($config['components']['db']);
$db->enableSchemaCache = false;
if ($db->driverName !== 'mysql') {
    throw new RuntimeException('This regression test requires MySQL/MariaDB.');
}

$original = "Bis bald \u{1F60A} \u{1F44B}\u{1F3FD} \u{1F469}\u{200D}\u{1F4BB} \u{2763}\u{FE0F} \u{00E4}\u{00F6}\u{00FC} :smile:";
$edited = $original . " \u{1F680}";
$messageTable = '{{%conversation_unicode_message_probe}}';
$revisionTable = '{{%conversation_unicode_revision_probe}}';
$tables = [];
$checks = 0;
try {
    foreach ([$messageTable => '{{%conversation_message}}', $revisionTable => '{{%conversation_message_revision}}'] as $probe => $source) {
        $db->createCommand('CREATE TEMPORARY TABLE ' . $probe . ' LIKE ' . $source)->execute();
        $tables[] = $probe;
    }
    $db->createCommand()->insert($messageTable, ['conversation_id' => 1, 'message' => $original])->execute();
    $id = $db->getLastInsertID();
    $read = static fn() => $db->createCommand('SELECT [[message]] FROM ' . $messageTable . ' WHERE [[id]]=:id', [':id' => $id])->queryScalar();
    if ($read() !== $original) {
        throw new RuntimeException('Native keyboard emoji corrupted when saving a message.');
    }
    ++$checks;
    $db->createCommand()->update($messageTable, ['message' => $edited], ['id' => $id])->execute();
    if ($read() !== $edited) {
        throw new RuntimeException('Native keyboard emoji corrupted when editing a message.');
    }
    ++$checks;
    $db->createCommand()->insert($revisionTable, [
        'message_id' => $id, 'editor_id' => 1, 'previous_message' => $original,
        'revised_message' => $edited, 'edited_at' => date('Y-m-d H:i:s'),
    ])->execute();
    $revision = $db->createCommand('SELECT [[previous_message]], [[revised_message]] FROM ' . $revisionTable)->queryOne();
    foreach (['previous_message' => $original, 'revised_message' => $edited] as $column => $expected) {
        if ($revision[$column] !== $expected) {
            throw new RuntimeException('Unicode edit history corrupted: ' . $column);
        }
        ++$checks;
    }
} finally {
    foreach (array_reverse($tables) as $table) {
        $db->createCommand('DROP TEMPORARY TABLE ' . $table)->execute();
    }
}
echo "PASS: {$checks} Unicode storage checks; persistent chat data unchanged.\n";

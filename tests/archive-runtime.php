<?php
$root = rtrim((string) ($argv[1] ?? ''), '/');
if (!str_ends_with($root, '/testcommunity.selbstsein.events')) {
    throw new RuntimeException('Use only the dedicated testcommunity.selbstsein.events installation.');
}
$protected = $root . '/protected';
$loader = require $protected . '/vendor/autoload.php';
Dotenv\Dotenv::createMutable($root, '.env')->safeLoad();
$loader->addClassMap(['humhub\\services\\BootstrapService' => $protected . '/humhub/services/BootstrapService.php']);
define('YII_DEBUG', false); define('YII_ENV', 'prod');
require $protected . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@humhub', $protected . '/humhub');
$_SERVER['REQUEST_URI'] = '/';
$config = (new humhub\services\BootstrapService())->getConfig('web');
$config['components']['request']['scriptFile'] = $root . '/index.php';
$config['components']['request']['scriptUrl'] = '/index.php';
$config['components']['request']['hostInfo'] = 'https://testcommunity.selbstsein.events';
new humhub\components\Application($config);
set_exception_handler(static function ($e) { fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() . "\n"); exit(1); });
function verify($condition, $name) { if (!$condition) { throw new RuntimeException($name); } $GLOBALS['checks'][] = $name; }
$checks = [];
$users = humhub\modules\user\models\User::find()->all();
$admin = null; $member = null;
$candidate = humhub\modules\conversations\models\Conversation::find()->one();
foreach ($users as $user) { if ($candidate->content->canArchive($user)) { $admin = $user; break; } }
verify($admin !== null, 'administrator available');
Yii::$app->user->switchIdentity($admin);
$c = humhub\modules\conversations\models\Conversation::find()->readable($admin)->one();
verify($c !== null, 'readable chat available');
$service = new humhub\modules\conversations\services\ConversationOverviewService();
$contains = static function ($groups, $id) { foreach ($groups as $g) { foreach ($g['conversations'] as $item) { if ((int)$item->id === (int)$id) { return true; } } } return false; };
verify(str_starts_with($c->content->container->getUrl(), '/'), 'Space URL is absolute from site root');
verify(!str_contains($c->url, 'contentContainer'), 'chat URL resolves Space context');
if (($argv[2] ?? '') !== 'write-test') { echo json_encode($checks) . "\n"; exit; }
verify(str_ends_with($root, '/testcommunity.selbstsein.events'), 'writes restricted to named test installation');
$transaction = Yii::$app->db->beginTransaction();
try {
    Yii::$app->request->setQueryParams(['cguid' => $c->content->container->guid]);
    $controller = new humhub\modules\conversations\controllers\ConversationController('conversation', Yii::$app->getModule('conversations'));
    $controller->contentContainer = $c->content->container;
    Yii::$app->controller = $controller;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    Yii::$app->request->setBodyParams(['archived'=>'1']);
    $controller->actionSetArchived((int)$c->id);
    verify(!$contains($service->groupedBySpace($admin), $c->id), 'archived chat excluded from active overview');
    verify($contains($service->groupedBySpace($admin, true), $c->id), 'archived chat included in archive');
    $c->content->refresh();
    $html = Yii::$app->view->renderFile($root . '/protected/modules/conversations/views/conversation/_archive-control.php', ['conversation'=>$c], $controller);
    verify(str_contains($html, 'Wiederherstellen'), 'admin sees restore control');
    foreach ($users as $user) { if (!$c->content->canArchive($user) && $c->content->canView($user)) { $member = $user; break; } }
    verify($member !== null, 'ordinary readable member available');
    Yii::$app->user->switchIdentity($member);
    $c = humhub\modules\conversations\models\Conversation::findOne($c->id);
    $html = Yii::$app->view->renderFile($root . '/protected/modules/conversations/views/conversation/_archive-control.php', ['conversation'=>$c], $controller);
    verify(!str_contains($html, '<form'), 'ordinary member has no archive control');
    try { $controller->actionSetArchived((int)$c->id); throw new RuntimeException('member archive accepted'); }
    catch (yii\web\ForbiddenHttpException $e) { $checks[] = 'ordinary member archive request forbidden'; }
    Yii::$app->user->switchIdentity(null);
    verify(!$c->content->canArchive(), 'guest cannot archive');
    Yii::$app->user->switchIdentity($admin);
    Yii::$app->request->setBodyParams(['archived'=>'0']);
    $controller->actionSetArchived((int)$c->id);
    verify($contains($service->groupedBySpace($admin), $c->id), 'restored chat returns to active overview');
    verify(!$contains($service->groupedBySpace($admin,true), $c->id), 'restored chat leaves archive');
    $overview = new humhub\modules\conversations\controllers\OverviewController('overview', Yii::$app->getModule('conversations'));
    Yii::$app->controller = $overview;
    verify(str_contains($overview->actionIndex(), 'Archivierte Chats anzeigen'), 'active overview renders');
    verify(str_contains($overview->actionIndex(true), 'Aktive Chats anzeigen'), 'archive overview renders');
} finally { $transaction->rollBack(); }
echo json_encode($checks, JSON_UNESCAPED_SLASHES) . "\n";
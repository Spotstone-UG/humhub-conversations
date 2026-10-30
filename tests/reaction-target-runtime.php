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
use humhub\modules\comment\models\Comment;
use humhub\modules\conversations\controllers\ContentReactionController;
use humhub\modules\conversations\services\ContentEmojiReactionService;
use humhub\modules\conversations\widgets\ContentEmojiReactionLink;
use humhub\modules\user\models\User;
$comment = null;
foreach (User::find()->where(['status' => 1])->all() as $user) {
    if ($user->isSystemAdmin()) { continue; }
    Yii::$app->user->switchIdentity($user);
    foreach (Comment::find()->limit(100)->all() as $candidate) {
        if (ContentEmojiReactionLink::supports($candidate)) { $comment = $candidate; break 2; }
    }
}
verify($comment !== null, 'ordinary member and readable comment available');
$controller = new ContentReactionController('content-reaction', Yii::$app->getModule('conversations'));
$controller->parentContentAddon = $comment;
$controller->parentContent = $comment->getSource();
$method = new ReflectionMethod($controller, 'content');
$target = $method->invoke($controller);
verify($target === $comment, 'comment reaction resolves to comment rather than source post');
$service = new ContentEmojiReactionService();
$transaction = Yii::$app->db->beginTransaction();
try {
    $beforeParent = $service->summary($controller->parentContent, $user);
    $beforeComment = $service->summary($comment, $user);
    $service->toggle($target, $user, '👍');
    verify($service->summary($comment, $user) !== $beforeComment, 'reaction changes on selected comment');
    verify($service->summary($controller->parentContent, $user) === $beforeParent, 'source post reactions remain unchanged');
    $service->toggle($target, $user, '👍');
    verify($service->summary($comment, $user) === $beforeComment, 'second toggle restores comment reaction');
    $controller->parentContentAddon = null;
    verify($method->invoke($controller) === $controller->parentContent, 'direct post reactions still target the post');
    Yii::$app->user->switchIdentity(null);
    try { $method->invoke($controller); throw new RuntimeException('guest accepted'); }
    catch (yii\web\ForbiddenHttpException $e) { $checks[] = 'guest reactions rejected'; }
} finally { $transaction->rollBack(); }
echo json_encode($checks, JSON_UNESCAPED_SLASHES) . "\n";
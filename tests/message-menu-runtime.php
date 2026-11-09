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
Yii::setAlias('@humhub/modules/conversations', dirname(__DIR__));
Yii::setAlias('@conversations', dirname(__DIR__));
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\services\ConversationVisibilityService;
use humhub\modules\conversations\controllers\ConversationController;
use humhub\modules\user\models\User;
Yii::$app->set('queue', new class extends yii\base\Component { public function push($job) { return 1; } });
$member=null;$chat=null;
foreach(User::find()->where(['status'=>1])->all() as $user){
    if($user->isSystemAdmin())continue;
    Yii::$app->user->switchIdentity($user);
    foreach(Conversation::find()->all() as $candidate){
        if(!$candidate->content->canEdit() && (new ConversationVisibilityService())->canWrite($candidate)){$member=$user;$chat=$candidate;break 2;}
    }
}
verify($member!==null,'ordinary participant without chat editing rights available');
$tx=Yii::$app->db->beginTransaction();
try {
    Yii::$app->db->createCommand()->update('content',['visibility'=>0],['id'=>$chat->content->id])->execute();
    $chat=Conversation::findOne($chat->id);
    $message=new ConversationMessage($chat->content->container);$message->conversation_id=$chat->id;$message->message='Menu permission regression';$message->content->visibility=$chat->content->visibility;
    verify($message->save(),'own test message created inside rollback transaction');
    Yii::$app->request->setQueryParams(['cguid'=>$chat->content->container->guid]);
    $controller=new ConversationController('conversation',Yii::$app->getModule('conversations'));
    $controller->contentContainer=$chat->content->container;Yii::$app->controller=$controller;
    $method=new ReflectionMethod($controller,'viewData');
    $view=$argv[2]??$root.'/protected/modules/conversations/views/conversation/view.php';
    $render=static function()use($method,$controller,$view,$chat){$params=$method->invoke($controller,Conversation::findOne($chat->id));return Yii::$app->view->renderFile($view,$params,$controller);};
    $article=static function($html,$id){$dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xp=new DOMXPath($dom);$node=$xp->query('//*[@id="conversation-message-'.$id.'"]')->item(0);if(!$node)throw new RuntimeException('message article missing');return $dom->saveHTML($node);};
    $html=$render();$own=$article($html,$message->id);
    verify(str_contains($own,'conversation-message__menu-trigger'),'ordinary participant sees own menu');
    verify(str_contains($own,'edit-message')&&str_contains($own,'delete-message'),'own menu offers edit and delete');
    verify(str_contains($own,'start-subconversation'),'ordinary posting member can start a subtopic');
    $foreign=ConversationMessage::find()->where(['conversation_id'=>$chat->id,'deleted_at'=>null])->andWhere(['<>','id',$message->id])->one();
    verify($foreign!==null,'foreign message available');$other=$article($html,$foreign->id);
    verify(!str_contains($other,'edit-message')&&!str_contains($other,'delete-message'),'foreign message cannot be edited or deleted');
    Conversation::updateAll(['closed_at'=>date('Y-m-d H:i:s')],['id'=>$chat->id]);
    $closed=$article($render(),$message->id);
    verify(str_contains($closed,'edit-message')&&str_contains($closed,'delete-message'),'own edit/delete remain available in closed chat');
    verify(!str_contains($closed,'start-subconversation'),'closed chat offers no subtopic action');
    ConversationMessage::updateAll(['deleted_at'=>date('Y-m-d H:i:s')],['id'=>$message->id]);
    verify(!str_contains($article($render(),$message->id),'conversation-message__menu-trigger'),'deleted message has no menu');
}finally{$tx->rollBack();}
echo json_encode($checks,JSON_UNESCAPED_SLASHES)."\n";





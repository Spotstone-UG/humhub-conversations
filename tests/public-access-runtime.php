<?php
// SPDX-License-Identifier: AGPL-3.0-only
require __DIR__.'/compat-bootstrap.php';
use humhub\modules\content\models\Content;
use humhub\modules\conversations\models\{Conversation,ConversationMessage,ConversationInterest};
use humhub\modules\conversations\services\{ConversationVisibilityService,ConversationOverviewService,ConversationService,ConversationInterestService,ConversationReactionService};
use humhub\modules\space\models\{Space,Membership};
use humhub\modules\user\models\User;
Yii::$app->set('queue',new class extends yii\base\Component { public array $jobs=[];public function push($job){$this->jobs[]=$job;return count($this->jobs);} });
$tx=Yii::$app->db->beginTransaction();
try {
    $users=[];
    foreach(['author','member','outsider','other'] as $role){$u=new User(['username'=>'public-'.$role,'email'=>'public-'.$role.'@example.test','status'=>User::STATUS_ENABLED,'visibility'=>User::VISIBILITY_REGISTERED_ONLY]);verifyCompat($u->save(),'User '.$role);$users[$role]=$u;}
    $space=new Space(['name'=>'Public access fixture','description'=>'','visibility'=>Space::VISIBILITY_REGISTERED_ONLY]);verifyCompat($space->save(),'Space fixture');$space->moduleManager->enable('conversations');
    foreach(['author','member'] as $role){$m=new Membership(['space_id'=>$space->id,'user_id'=>$users[$role]->id,'status'=>Membership::STATUS_MEMBER,'group_id'=>Space::USERGROUP_MEMBER]);verifyCompat($m->save(),'Membership '.$role);}
    Yii::$app->user->switchIdentity($users['author']);$chat=new Conversation($space);$chat->title='Public access chat';$chat->content->visibility=0;verifyCompat($chat->save(),'Private chat created');
    $access=new ConversationVisibilityService();$overview=new ConversationOverviewService();$interest=new ConversationInterestService();
    $groupFor=static function(User $u)use($overview,$chat){foreach($overview->groupedBySpace($u) as $g){foreach($g['conversations'] as $c){if((int)$c->id===(int)$chat->id)return $g;}}return null;};
    verifyCompat($groupFor($users['outsider'])===null,'Private chat absent from outsider overview');
    $access->change($chat,1);verifyCompat($access->mode($chat)===1,'Read-only scope saved');
    Yii::$app->user->switchIdentity($users['outsider']);
    $group=$groupFor($users['outsider']);verifyCompat($group!==null,'Public chat appears for nonmember');verifyCompat(!$group['isRelevant'],'Unsubscribed public chat placed below separator');
    verifyCompat(!$interest->isInterested($chat,$users['outsider']),'Public visibility does not subscribe users');
    $post=static function(Conversation $c){$m=new ConversationMessage($c->content->container);$m->message='Public access message';return(new ConversationService())->postMessage($c,$m,[],Yii::$app->security->generateRandomString(32));};
    verifyCompat(!$access->canWrite($chat),'Readonly outsider cannot write');
    Yii::$app->request->setQueryParams(['cguid'=>$space->guid]);
    $chatController=new humhub\modules\conversations\controllers\ConversationController('conversation',Yii::$app->getModule('conversations'));Yii::$app->controller=$chatController;
    $chatController->action=$chatController->createAction('view');verifyCompat($chatController->beforeAction($chatController->action),'Nonmember can enter public chat controller');
    $readOnlyHtml=$chatController->actionView($chat->id);verifyCompat(str_contains($readOnlyHtml,'mitlesen und abonnieren')&&!str_contains($readOnlyHtml,'class="conversation-composer"'),'Readonly view has explanation and no composer');
    try{$post($chat);throw new RuntimeException('Readonly posting accepted');}catch(yii\web\ForbiddenHttpException $e){verifyCompat(true,'Forged readonly post rejected');}
    $interest->toggle($chat,$users['outsider']);verifyCompat($groupFor($users['outsider'])['isRelevant'],'Explicit chat subscription promotes relevance');
    Yii::$app->user->switchIdentity($users['author']);$memberPost=$post($chat);verifyCompat($memberPost->id>0,'Members retain write access in readonly mode');
    $queued=Yii::$app->queue->jobs;$last=end($queued);verifyCompat(in_array($users['outsider']->id,$last->query->column(),false),'Subscribed outsider receives new message notification');verifyCompat(!in_array($users['member']->id,$last->query->column(),false),'Unsubscribed member receives no unsolicited notice');
    $access->change($chat,2);verifyCompat($access->mode(Conversation::findOne($chat->id))===2,'Public writing scope persists');
    Yii::$app->user->switchIdentity($users['outsider']);$chat=Conversation::findOne($chat->id);verifyCompat($access->canWrite($chat),'Explicit public mode permits outside writer');
    verifyCompat(str_contains($chatController->actionView($chat->id),'class="conversation-composer"'),'Outside writer sees composer');
    $outsiderPost=$post($chat);verifyCompat($outsiderPost->id>0,'Outside writer can actually post');verifyCompat(ConversationMessage::findOne($outsiderPost->id)->content->isPublic(),'Outside message retains parent visibility');verifyCompat(ConversationMessage::findOne($outsiderPost->id)->content->canView($users['other']),'Outside messages readable by another community user');
    try{$access->change($chat,0);throw new RuntimeException('Non-editor scope change accepted');}catch(yii\web\ForbiddenHttpException $e){verifyCompat(true,'Public writing grants no chat administration');}
    Yii::$app->user->switchIdentity(null);verifyCompat(!$access->canWrite($chat),'Guest writing always denied');
    Yii::$app->user->switchIdentity($users['author']);$chat=Conversation::findOne($chat->id);$access->change($chat,1);
    Yii::$app->user->switchIdentity($users['outsider']);$chat=Conversation::findOne($chat->id);verifyCompat(!$access->canWrite($chat),'Downgrade immediately removes outside write permission');
    try{(new ConversationReactionService())->toggle($outsiderPost,$users['outsider'],'👍');throw new RuntimeException('Readonly reaction accepted');}catch(yii\web\ForbiddenHttpException $e){verifyCompat(true,'Readonly reactions denied');}
    try{(new ConversationService())->editMessage($chat,$outsiderPost);throw new RuntimeException('Readonly edit accepted');}catch(yii\web\ForbiddenHttpException $e){verifyCompat(true,'Readonly edits denied');}
    $interest->toggle($chat,$users['outsider']);verifyCompat(!$groupFor($users['outsider'])['isRelevant'],'Unsubscribing moves chat back below separator');verifyCompat($overview->unreadTotal($users['outsider'])===0,'Additional chats do not create red unread badge');
    $controller=new humhub\modules\conversations\controllers\OverviewController('overview',Yii::$app->getModule('conversations'));Yii::$app->controller=$controller;
    $html=$controller->actionIndex();verifyCompat(str_contains($html,'Weitere lesbare Chats')&&str_contains($html,'Public access chat'),'Overview renders additional public chats');
    $start=new humhub\modules\sociocraticGovernance\controllers\StartController('start',Yii::$app->getModule('sociocratic-governance'));Yii::$app->controller=$start;
    $sidebar=$start->renderPartial('_navigation',['chatGroups'=>$overview->groupedBySpace($users['outsider']),'navigationSpaces'=>[],'navigationProjectCircles'=>[],'navigationCompetenceCircles'=>[]]);verifyCompat(str_contains($sidebar,'Weitere lesbare Chats')&&str_contains($sidebar,'Public access chat'),'Start sidebar renders same additional chats');
    Yii::$app->user->switchIdentity($users['author']);$access->change($chat,0);verifyCompat($groupFor($users['outsider'])===null,'Making chat invisible removes it from outsider overview');
    echo Yii::$app->version.": $count public access, writing, relevance and subscription checks passed\n";
}finally{Yii::$app->user->switchIdentity($admin);if($tx->isActive)$tx->rollBack();}

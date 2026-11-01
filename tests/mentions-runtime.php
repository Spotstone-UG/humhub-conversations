<?php
// SPDX-License-Identifier: AGPL-3.0-only
require __DIR__ . '/compat-bootstrap.php';

use humhub\modules\content\models\Content;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\services\ConversationInterestService;
use humhub\modules\conversations\services\ConversationMentionService;
use humhub\modules\conversations\controllers\ConversationController;
use humhub\modules\space\models\Space;
use humhub\modules\space\models\Membership;
use humhub\modules\user\models\User;
use humhub\modules\user\models\Mentioning;
use humhub\modules\user\notifications\Mentioned;
use humhub\modules\sociocraticGovernance\models\Circle;

// Capture queued jobs: never deliver test web/mail/push notifications.
final class MentionTestQueue extends yii\base\Component
{
    public array $jobs = [];
    public function push($job) { $this->jobs[] = $job; return count($this->jobs); }
}
$queue = new MentionTestQueue();
Yii::$app->set('queue', $queue);
$tx = Yii::$app->db->beginTransaction();
try {
    $users = [];
    foreach (['author', 'member', 'outsider', 'disabled'] as $name) {
        $user = new User(['username' => 'mentions-' . $name, 'email' => 'mentions-' . $name . '@example.test',
            'status' => $name === 'disabled' ? User::STATUS_DISABLED : User::STATUS_ENABLED,
            'visibility' => User::VISIBILITY_REGISTERED_ONLY]);
        verifyCompat($user->save(), 'User fixture ' . $name);
        $users[$name] = $user;
    }
    $space = new Space(['name' => 'Mention test', 'visibility' => Space::VISIBILITY_ALL]);
    verifyCompat($space->save(), 'Space created');
    $space->moduleManager->enable('conversations');
    foreach (['author', 'member'] as $name) {
        $membership = new Membership(['space_id' => $space->id, 'user_id' => $users[$name]->id,
            'status' => Membership::STATUS_MEMBER, 'group_id' => Space::USERGROUP_MEMBER]);
        verifyCompat($membership->save(), 'Member fixture ' . $name);
    }
    Yii::$app->user->switchIdentity($users['author']);
    $conversation = new Conversation($space);
    $conversation->title = 'Mentions';
    $conversation->content->visibility = Content::VISIBILITY_PUBLIC;
    verifyCompat($conversation->save(), 'Ordinary member creates chat');
    $service = new ConversationMentionService();
    verifyCompat(!$service->isCircle($space), 'Ordinary space is not a circle');
    verifyCompat($service->canMention($space, $users['member']), 'Member selectable');
    verifyCompat(!$service->canMention($space, $users['outsider']), 'Nonmember excluded by default');
    $makeMessage = static function (User $target) use ($conversation, $space): ConversationMessage {
        $message = new ConversationMessage($space);
        $message->conversation_id = $conversation->id;
        $message->content->visibility = $conversation->content->visibility;
        $message->message = '[Person](mention:' . $target->guid . ')';
        return $message;
    };
    $forged = $makeMessage($users['outsider']);
    verifyCompat(!$forged->save() && $forged->hasErrors('message'), 'Forged nonmember mention rejected server-side');
    $circle = new Circle(['space_id' => $space->id]);
    verifyCompat($circle->save(false), 'Circle fixture');
    verifyCompat($service->isCircle($space), 'Actual governance circle detected');
    verifyCompat($service->canMention($space, $users['outsider']), 'Circle permits community member');
    verifyCompat(!$service->canMention($space, $users['disabled']), 'Disabled account excluded');
    Yii::$app->request->setQueryParams(['cguid' => $space->guid]);
    $controller = new ConversationController('conversation', Yii::$app->getModule('conversations'));
    Yii::$app->controller = $controller;
    Yii::$app->request->setQueryParams(['keyword' => 'mentions-outsider']);
    $results = $controller->actionMentioning($conversation->id)->data;
    verifyCompat(in_array($users['outsider']->guid, array_column($results, 'guid'), true), 'Circle autocomplete returns outsider');
    $noticeCount = static fn(): int => count(array_filter($queue->jobs,
        static fn($job): bool => isset($job->notification) && $job->notification instanceof Mentioned));
    $before = $noticeCount();
    $message = $makeMessage($users['outsider']);
    verifyCompat($message->save(), 'Circle mention saved');
    verifyCompat($noticeCount() === $before + 1, 'Exactly one first mention notice queued');
    $chatJobs = static fn(): array => array_values(array_filter($queue->jobs,
        static fn($job): bool => isset($job->notification) && $job->notification instanceof \humhub\modules\conversations\notifications\ChatNotification));
    $initialChatJobs = $chatJobs();
    foreach ($initialChatJobs as $job) {
        verifyCompat(!in_array($users['outsider']->id, $job->query->column(), false), 'First mention does not duplicate ordinary chat notice');
    }
    $mentionJob = array_values(array_filter($queue->jobs, static fn($job): bool => isset($job->notification) && $job->notification instanceof Mentioned))[0];
    verifyCompat((int) $mentionJob->notification->originator->id === (int) $users['author']->id, 'Mention attributed to message author');
    verifyCompat((new ConversationInterestService())->isInterested($conversation, $users['outsider']), 'Whole chat followed');
    verifyCompat(Mentioning::find()->where(['object_model' => ConversationMessage::class, 'object_id' => $message->id,
        'user_id' => $users['outsider']->id])->exists(), 'Message @ indicator retained');
    $message->message .= ' edited';
    verifyCompat($message->save(), 'Mention edited');
    verifyCompat($noticeCount() === $before + 1, 'Editing does not repeat mention notice');
    $jobs = $chatJobs();
    $editedJob = end($jobs);
    verifyCompat($editedJob->notification->eventType === 'edited'
        && in_array($users['outsider']->id, $editedJob->query->column(), false), 'Follower receives edited-message notice');
    $second = $makeMessage($users['outsider']);
    verifyCompat($second->save(), 'Second message mention saved');
    verifyCompat($noticeCount() === $before + 1, 'Later messages do not repeat first-chat notice');
    $jobs = $chatJobs();
    $laterJob = end($jobs);
    verifyCompat(in_array($users['outsider']->id, $laterJob->query->column(), false), 'Follower receives subsequent messages');
    $conversation->summary = 'Changed summary';
    verifyCompat($conversation->save(), 'Chat description updated');
    $jobs = $chatJobs();
    $changeJob = end($jobs);
    verifyCompat($changeJob->notification->eventType === 'changed'
        && in_array($users['outsider']->id, $changeJob->query->column(), false), 'Follower receives chat update');
    (new ConversationInterestService())->toggle($conversation, $users['outsider']);
    $third = $makeMessage($users['outsider']);
    verifyCompat($third->save(), 'Mention after opting out saved');
    verifyCompat(!(new ConversationInterestService())->isInterested($conversation, $users['outsider']), 'Explicit opt-out preserved');
    $conversation->content->visibility = Content::VISIBILITY_PRIVATE;
    verifyCompat($conversation->save(), 'Private chat saved');
    $private = $makeMessage($users['outsider']);
    verifyCompat($private->save(), 'Private circle mention text saved');
    verifyCompat($noticeCount() === $before + 1, 'Private chat does not disclose content to outsider');
    $laterJob->notification->about(ConversationMessage::findOne($second->id));
    verifyCompat($laterJob->notification->isBlockedForUser($users['outsider']), 'Delivery rechecks revoked read access');
    Yii::$app->user->switchIdentity($users['outsider']);
    try {
        $controller->actionMentioning($conversation->id);
        throw new RuntimeException('Private chat autocomplete accepted outsider');
    } catch (yii\web\HttpException $exception) {
        verifyCompat(in_array($exception->statusCode, [403, 404], true), 'Private autocomplete denied to outsider');
    }
    Yii::$app->user->switchIdentity($users['author']);
    $governance = Yii::$app->getModule('sociocratic-governance');
    Yii::$app->setModule('sociocratic-governance', null);
    verifyCompat(!$service->isCircle($space) && !$service->canMention($space, $users['outsider']), 'Missing optional governance module safely falls back');
    Yii::$app->setModule('sociocratic-governance', $governance);
    $circle->delete();
    verifyCompat(!$service->isCircle($space), 'Removing circle restores member-only default');
    $results = $controller->actionMentioning($conversation->id)->data;
    verifyCompat(!in_array($users['outsider']->guid, array_column($results, 'guid'), true), 'Ordinary autocomplete excludes outsider');
    echo Yii::$app->version . ": $count mention scope, follow, notification and permission checks passed\n";
} finally {
    // Do not leave a rolled-back fixture user in the persistent CLI session.
    Yii::$app->user->switchIdentity($admin);
    if ($tx->isActive) { $tx->rollBack(); }
}

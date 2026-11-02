<?php
// SPDX-License-Identifier: AGPL-3.0-only
require __DIR__ . '/compat-bootstrap.php';

use humhub\modules\content\models\Content;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\controllers\ConversationController;
use humhub\modules\conversations\services\ConversationService;
use humhub\modules\conversations\services\ConversationVisibilityService;
use humhub\modules\file\models\File;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;

Yii::$app->set('queue', new class extends yii\base\Component {
    public function push($job) { return 1; }
});
$tx = Yii::$app->db->beginTransaction();
try {
    $users = [];
    foreach (['author', 'member', 'outsider'] as $name) {
        $users[$name] = new User(['username' => 'visibility-' . $name, 'email' => 'visibility-' . $name . '@example.test',
            'status' => User::STATUS_ENABLED, 'visibility' => User::VISIBILITY_REGISTERED_ONLY]);
        verifyCompat($users[$name]->save(), 'User fixture ' . $name);
    }
    $space = new Space(['name' => 'Visibility test', 'visibility' => Space::VISIBILITY_REGISTERED_ONLY]);
    verifyCompat($space->save(), 'Space fixture');
    $space->moduleManager->enable('conversations');
    foreach (['author', 'member'] as $name) {
        $membership = new Membership(['space_id' => $space->id, 'user_id' => $users[$name]->id,
            'status' => Membership::STATUS_MEMBER, 'group_id' => Space::USERGROUP_MEMBER]);
        verifyCompat($membership->save(), 'Membership fixture ' . $name);
    }
    Yii::$app->user->switchIdentity($users['author']);
    $chat = new Conversation($space);
    $chat->title = 'Visibility';
    $chat->content->visibility = Content::VISIBILITY_PRIVATE;
    verifyCompat($chat->save(), 'Chat created by ordinary member');
    $post = static function (Conversation $chat): ConversationMessage {
        $message = new ConversationMessage($chat->content->container);
        $message->message = 'Permission test message';
        return (new ConversationService())->postMessage($chat, $message, [], Yii::$app->security->generateRandomString(32));
    };
    $message = $post($chat);
    $visibility = new ConversationVisibilityService();
    $readable = static function (int $id): bool {
        return Conversation::find()->readable()->andWhere(['conversation.id' => $id])->exists();
    };
    $fileFor = static function (int $id): File {
        $file = new File(['public' => false]);
        $file->setPolymorphicRelation(ConversationMessage::findOne($id));
        return $file;
    };
    $assertAccess = static function (bool $outside, string $phase) use ($chat, $message, $users, $readable, $fileFor): void {
        $parent = Conversation::findOne($chat->id);
        $child = ConversationMessage::findOne($message->id);
        verifyCompat($parent->content->canView($users['member']), $phase . ': member reads parent');
        verifyCompat($child->content->canView($users['member']), $phase . ': member reads message');
        verifyCompat($parent->content->canView($users['outsider']) === $outside, $phase . ': parent outsider access');
        verifyCompat($child->content->canView($users['outsider']) === $outside, $phase . ': message outsider access');
        verifyCompat($fileFor($message->id)->canView($users['outsider']) === $outside, $phase . ': attachment outsider access');
        Yii::$app->user->switchIdentity($users['outsider']);
        verifyCompat($readable($chat->id) === $outside, $phase . ': overview query outsider access');
        Yii::$app->user->switchIdentity(null);
        verifyCompat(!$readable($chat->id), $phase . ': registered Space stays unavailable to guests');
        verifyCompat(!$fileFor($message->id)->canView(), $phase . ': attachment unavailable to guests');
        Yii::$app->user->switchIdentity($users['author']);
    };
    $assertAccess(false, 'Initial private');
    verifyCompat($visibility->canShare($chat), 'Ordinary author may share to community');
    $visibility->change($chat, Content::VISIBILITY_PUBLIC);
    $assertAccess(true, 'Shared');
    $newPublic = $post($chat);
    verifyCompat($newPublic->content->canView($users['outsider']), 'New message inherits shared state');
    $visibility->change($chat, Content::VISIBILITY_PRIVATE);
    $assertAccess(false, 'Restricted again');
    verifyCompat(!ConversationMessage::findOne($newPublic->id)->content->canView($users['outsider']), 'Later message also becomes private');
    // A caller with a stale parent object must still use the current saved visibility.
    $stale = Conversation::findOne($chat->id);
    $stale->content->visibility = Content::VISIBILITY_PUBLIC;
    $newPrivate = $post($stale);
    verifyCompat(!$newPrivate->content->canView($users['outsider']), 'Posting refreshes stale permissions');
    $failMessageUpdate = static function ($event): void {
        if ($event->sender->getModel() instanceof ConversationMessage) { throw new RuntimeException('Simulated message save failure'); }
    };
    yii\base\Event::on(Content::class, Content::EVENT_BEFORE_UPDATE, $failMessageUpdate);
    try {
        try { $visibility->change($chat, Content::VISIBILITY_PUBLIC); throw new RuntimeException('Expected save failure missing'); }
        catch (RuntimeException $exception) {
            verifyCompat($exception->getMessage() === 'Simulated message save failure', 'Child save failure reported');
        }
    } finally { yii\base\Event::off(Content::class, Content::EVENT_BEFORE_UPDATE, $failMessageUpdate); }
    $assertAccess(false, 'Failed change rolled back');
    // The standard HumHub menu is covered by the same Content event.
    $chat->content->refresh();
    $chat->content->visibility = Content::VISIBILITY_PUBLIC;
    verifyCompat($chat->content->save(), 'Native content visibility save succeeds');
    $assertAccess(true, 'Native visibility control');
    foreach (['member', 'outsider'] as $role) {
        Yii::$app->user->switchIdentity($users[$role]);
        try {
            $visibility->change(Conversation::findOne($chat->id), Content::VISIBILITY_PRIVATE);
            throw new RuntimeException('Unauthorized change accepted');
        } catch (yii\web\ForbiddenHttpException $exception) {
            verifyCompat(true, $role . ' cannot change another author chat visibility');
        }
    }
    Yii::$app->user->switchIdentity($users['author']);
    try { $visibility->change($chat, 99); throw new RuntimeException('Invalid visibility accepted'); }
    catch (yii\web\BadRequestHttpException $exception) { verifyCompat(true, 'Invalid visibility rejected'); }
    Yii::$app->request->setQueryParams(['cguid' => $space->guid]);
    $controller = new ConversationController('conversation', Yii::$app->getModule('conversations'));
    Yii::$app->controller = $controller;
    $modal = $controller->actionVisibility($chat->id);
    verifyCompat(str_contains($modal, 'Sichtbarkeit speichern') && str_contains($modal, 'value="0"') && str_contains($modal, 'value="1"'), 'Editor sees both visibility choices');
    $visibility->change($chat, Content::VISIBILITY_PRIVATE);
    $space->visibility = Space::VISIBILITY_NONE;
    verifyCompat($space->save(), 'Space made private');
    $privateChat = Conversation::findOne($chat->id);
    verifyCompat(!$visibility->canShare($privateChat), 'Private Space cannot share outside');
    try { $visibility->change($privateChat, Content::VISIBILITY_PUBLIC); throw new RuntimeException('Private Space bypass accepted'); }
    catch (yii\web\ForbiddenHttpException $exception) { verifyCompat(true, 'Private Space boundary enforced'); }
    echo Yii::$app->version . ": $count visibility, attachment and permission checks passed\n";
} finally {
    Yii::$app->user->switchIdentity($admin);
    if ($tx->isActive) { $tx->rollBack(); }
}

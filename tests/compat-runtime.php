<?php
// SPDX-License-Identifier: AGPL-3.0-only
require __DIR__ . '/compat-bootstrap.php';
use humhub\modules\comment\models\Comment;
use humhub\modules\conversations\controllers\ContentReactionController;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ContentEmojiReaction;
use humhub\modules\conversations\services\ConversationOverviewService;
use humhub\modules\conversations\widgets\ContentEmojiReactionLink;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use humhub\modules\content\models\Content;
$db = Yii::$app->db;
$tx = $db->beginTransaction();
try {
    $space = new Space(['name' => 'Private compatibility space', 'visibility' => Space::VISIBILITY_NONE]);
    verifyCompat($space->save(), 'Private space saved');
    $outsider = new User(['username' => 'compat-outsider', 'email' => 'compat-outsider@example.invalid', 'status' => User::STATUS_ENABLED]);
    verifyCompat($outsider->save(), 'Ordinary outsider saved');
    $post = new Post($space, ['message' => 'Private test post']);
    $post->content->visibility = Content::VISIBILITY_PRIVATE;
    $post->silentContentCreation = true; $post->autoFollow = false;
    verifyCompat($post->save(), 'Private post saved');
    $space->moduleManager->enable('conversations');
    $conversation = new Conversation($space, ['title' => 'Private test conversation']);
    $conversation->content->visibility = Content::VISIBILITY_PRIVATE;
    $conversation->silentContentCreation = true; $conversation->autoFollow = false;
    verifyCompat($conversation->save(), 'Private chat saved');
    verifyCompat((new ConversationOverviewService())->groupedBySpace($outsider) === [], 'Explicit outsider does not inherit admin session visibility');
    verifyCompat(count((new ConversationOverviewService())->groupedBySpace($admin)) === 1, 'Explicit admin can read the chat');
    verifyCompat(ContentEmojiReactionLink::supports($post), 'Post reaction supported through correct core API');
    $attributes = ['message' => 'Comment', 'created_by' => $admin->id, 'updated_by' => $admin->id,
        'created_at' => '2026-01-02 12:00:00', 'updated_at' => '2026-01-02 12:00:00'];
    $attributes += isset(Comment::getTableSchema()->columns['parent_comment_id'])
        ? ['content_id' => $post->content->id, 'parent_comment_id' => null]
        : ['object_model' => Post::class, 'object_id' => $post->id];
    $db->createCommand()->insert(Comment::tableName(), $attributes)->execute();
    $comment = Comment::findOne($db->getLastInsertID());
    verifyCompat(ContentEmojiReactionLink::supports($comment), 'Comment reaction supported');
    foreach ([$post, $comment] as $object) {
        $controller = new ContentReactionController('content-reaction', Yii::$app->getModule('conversations'));
        Yii::$app->controller = $controller;
        Yii::$app->request->setQueryParams(['contentModel' => $object::class, 'contentId' => $object->id]);
        verifyCompat($controller->beforeAction($controller->action = $controller->createAction('picker')), 'Controller resolves readable target');
        $method = new ReflectionMethod($controller, 'content');
        verifyCompat($method->invoke($controller)->id === $object->id, 'Controller resolves exact requested item');
        $service = new humhub\modules\conversations\services\ContentEmojiReactionService();
        $service->toggle($object, $admin, '👍');
        verifyCompat(count($service->summary($object, $admin)) === 1, 'Reaction persisted');
        $service->toggle($object, $admin, '👍');
        verifyCompat($service->summary($object, $admin) === [], 'Second click removes reaction');
        Yii::$app->user->switchIdentity($outsider);
        verifyCompat(!ContentEmojiReactionLink::supports($object), 'Forbidden private target has no reaction control');
        try {
            $blocked = new ContentReactionController('content-reaction', Yii::$app->getModule('conversations'));
            Yii::$app->controller = $blocked;
            $blocked->beforeAction($blocked->action = $blocked->createAction('picker'));
            throw new RuntimeException('Private target accepted');
        } catch (yii\web\ForbiddenHttpException $e) { verifyCompat(true, 'Private endpoint access denied'); }
        Yii::$app->user->switchIdentity($admin);
    }
    Yii::$app->request->setQueryParams(['contentModel' => stdClass::class, 'contentId' => 1]);
    try {
        $invalid = new ContentReactionController('content-reaction', Yii::$app->getModule('conversations'));
        Yii::$app->controller = $invalid;
        $invalid->beforeAction($invalid->action = $invalid->createAction('picker'));
        throw new RuntimeException('Arbitrary model class accepted');
    } catch (yii\web\BadRequestHttpException $e) { verifyCompat(true, 'Arbitrary model class denied'); }
    echo Yii::$app->version . ": $count reaction, visibility and interception integration checks passed\n";
} finally { if ($tx->isActive) { $tx->rollBack(); } }

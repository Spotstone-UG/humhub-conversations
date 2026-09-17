<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\services\ConversationService;
use humhub\modules\conversations\services\ConversationStateService;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\NotFoundHttpException;

final class ConversationController extends ContentContainerController
{
    public $validContentContainerClasses = [Space::class];

    public function actionIndex(): string
    {
        $conversations = Conversation::find()
            ->contentContainer($this->contentContainer)
            ->readable()
            ->orderBy(['conversation.last_message_at' => SORT_DESC, 'conversation.id' => SORT_DESC])
            ->all();

        return $this->render('index', ['conversations' => $conversations, 'contentContainer' => $this->contentContainer]);
    }

    public function actionCreate()
    {
        $conversation = new Conversation($this->contentContainer);
        if (!$conversation->content->canEdit()) {
            $this->forbidden();
        }

        if ($conversation->load(Yii::$app->request->post())) {
            $conversation = (new ConversationService())->create($conversation, $this->contentContainer);
            return $this->redirect($conversation->url);
        }

        return $this->render('create', ['conversation' => $conversation, 'contentContainer' => $this->contentContainer]);
    }

    public function actionView(int $id): string
    {
        $conversation = $this->findConversation($id);
        $stateService = new ConversationStateService();
        $unreadCount = $stateService->unreadCount($conversation, Yii::$app->user->identity);
        $firstUnreadMessageId = $stateService->firstUnreadMessageId($conversation, Yii::$app->user->identity);
        $messages = $conversation->getMessages()->all();
        $lastShownMessageId = $messages ? (int) end($messages)->id : null;

        // Use the display snapshot: messages arriving during this request remain unread.
        $stateService->markSeen($conversation, Yii::$app->user->identity, $lastShownMessageId);

        return $this->render('view', [
            'conversation' => $conversation,
            'messages' => $messages,
            'unreadCount' => $unreadCount,
            'firstUnreadMessageId' => $firstUnreadMessageId,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    public function actionMessage(int $conversationId)
    {
        $this->forcePostRequest();
        $conversation = $this->findConversation($conversationId);
        $message = new ConversationMessage($conversation->content->container);
        $message->load(Yii::$app->request->post());

        $createdMessage = (new ConversationService())->postMessage(
            $conversation,
            $message,
            (array) Yii::$app->request->post('fileList', []),
        );
        (new ConversationStateService())->markSeen($conversation, Yii::$app->user->identity, $createdMessage->id);

        return $this->redirect($conversation->url . '#conversation-message-' . $createdMessage->id);
    }

    public function actionReceipts(int $conversationId, int $messageId): string
    {
        $conversation = $this->findConversation($conversationId);
        $message = ConversationMessage::find()->where(['id' => $messageId, 'conversation_id' => $conversation->id])->one();
        if ($message === null) {
            throw new NotFoundHttpException();
        }

        $receipts = (new ConversationStateService())->visibleReadReceipts($message, Yii::$app->user->identity);
        return $this->renderAjax('receipts', ['message' => $message, 'receipts' => $receipts]);
    }

    private function findConversation(int $id): Conversation
    {
        $conversation = Conversation::find()
            ->contentContainer($this->contentContainer)
            ->readable()
            ->where(['conversation.id' => $id])
            ->one();
        if ($conversation === null) {
            throw new NotFoundHttpException();
        }
        return $conversation;
    }
}


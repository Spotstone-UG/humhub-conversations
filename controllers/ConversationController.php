<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\controllers;

use humhub\modules\content\components\ContentContainerController;
use humhub\modules\content\widgets\WallCreateContentForm;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\services\ConversationService;
use humhub\modules\conversations\services\ConversationReactionService;
use humhub\modules\conversations\services\ConversationStateService;
use humhub\modules\space\models\Space;
use humhub\modules\conversations\widgets\ConversationForm;
use humhub\modules\conversations\services\EmojiPaletteService;
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

    /**
     * Returns the Conversation form for HumHub's standard stream composer.
     */
    public function actionCreateForm(): string
    {
        if (!(new Conversation($this->contentContainer))->content->canEdit()) {
            $this->forbidden();
        }

        return $this->renderAjaxPartial(ConversationForm::widget([
            'contentContainer' => $this->contentContainer,
        ]));
    }

    /**
     * Creates a Conversation from the standard stream composer.
     */
    public function actionPost()
    {
        $this->forcePostRequest();
        $conversation = new Conversation($this->contentContainer);
        if (!$conversation->content->canEdit()) {
            $this->forbidden();
        }

        $conversation->load(Yii::$app->request->post(), 'Conversation');

        return Conversation::getDb()->transaction(
            fn() => WallCreateContentForm::create($conversation, $this->contentContainer),
        );
    }

    public function actionView(int $id): string
    {
        $conversation = $this->findConversation($id);
        $stateService = new ConversationStateService();
        $unreadCount = $stateService->unreadCount($conversation, Yii::$app->user->identity);
        $firstUnreadMessageId = $stateService->firstUnreadMessageId($conversation, Yii::$app->user->identity);
        $messages = $conversation->getMessages()->all();
        $subconversationsByOrigin = [];
        foreach ($conversation->subconversations as $subconversation) {
            if ($subconversation->origin_message_id !== null) {
                $subconversationsByOrigin[(int) $subconversation->origin_message_id][] = $subconversation;
            }
        }
        $lastShownMessageId = $messages ? (int) end($messages)->id : null;

        // Use the display snapshot: messages arriving during this request remain unread.
        $stateService->markSeen($conversation, Yii::$app->user->identity, $lastShownMessageId);

        return $this->render('view', [
            'conversation' => $conversation,
            'messages' => $messages,
            'unreadCount' => $unreadCount,
            'firstUnreadMessageId' => $firstUnreadMessageId,
            'subconversationsByOrigin' => $subconversationsByOrigin,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    public function actionMessage(int $conversationId)
    {
        $this->forcePostRequest();
        $conversation = $this->findConversation($conversationId);
        if ($conversation->isClosed) {
            throw new \yii\web\ForbiddenHttpException('Diese Conversation ist beendet.');
        }
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

    /**
     * Opens the edit dialog for an author's own message and persists the
     * changed text with an auditable edited_at marker.
     */
    public function actionEditMessage(int $conversationId, int $messageId)
    {
        $conversation = $this->findConversation($conversationId);
        $message = $this->findMessage($conversation, $messageId);

        if ((int) $message->content->created_by !== (int) Yii::$app->user->id || $message->isDeleted) {
            $this->forbidden();
        }

        if (Yii::$app->request->isPost) {
            $this->forcePostRequest();
            $message->load(Yii::$app->request->post());

            if ((new ConversationService())->editMessage($conversation, $message)) {
                return $this->redirect($conversation->url . '#conversation-message-' . $message->id);
            }

            Yii::$app->response->statusCode = 400;
        }

        return $this->renderAjax('edit-message', [
            'conversation' => $conversation,
            'message' => $message,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    /** Lets every Space member who may post start a linked, separate chat. */
    public function actionStartSubconversation(int $conversationId, int $messageId)
    {
        $parent = $this->findConversation($conversationId);
        $origin = $this->findMessage($parent, $messageId);
        $subconversation = new Conversation($this->contentContainer);

        if (!$subconversation->content->canEdit()) {
            $this->forbidden();
        }

        if (Yii::$app->request->isPost) {
            $this->forcePostRequest();
            $subconversation->load(Yii::$app->request->post());
            $subconversation = (new ConversationService())->createSubconversation($parent, $origin, $subconversation);

            return $this->redirect($subconversation->url);
        }

        return $this->renderAjax('start-subconversation', [
            'parent' => $parent,
            'origin' => $origin,
            'subconversation' => $subconversation,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    /** Presents confirmation then leaves a permanent, content-free timeline marker. */
    public function actionDeleteMessage(int $conversationId, int $messageId)
    {
        $conversation = $this->findConversation($conversationId);
        $message = $this->findMessage($conversation, $messageId);
        if ((int) $message->content->created_by !== (int) Yii::$app->user->id || $message->isDeleted) {
            $this->forbidden();
        }

        if (Yii::$app->request->isPost) {
            $this->forcePostRequest();
            (new ConversationService())->deleteMessage($conversation, $message);
            return $this->redirect($conversation->url . '#conversation-message-' . $message->id);
        }

        return $this->renderAjax('delete-message', [
            'conversation' => $conversation,
            'message' => $message,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    public function actionClose(int $conversationId)
    {
        $conversation = $this->findConversation($conversationId);
        if (!$conversation->content->canEdit()) {
            $this->forbidden();
        }

        if (Yii::$app->request->isPost) {
            $this->forcePostRequest();
            $outcome = (string) Yii::$app->request->post('outcome');
            (new ConversationService())->close($conversation, $outcome);
            return $this->redirect($conversation->url);
        }

        return $this->renderAjax('close', [
            'conversation' => $conversation,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    public function actionReopen(int $conversationId)
    {
        $this->forcePostRequest();
        $conversation = $this->findConversation($conversationId);
        (new ConversationService())->reopen($conversation);

        return $this->redirect($conversation->url);
    }

    /** Opens the complete HumHub Unicode emoji catalogue for a message. */
    public function actionReactionPicker(int $conversationId, int $messageId): string
    {
        $conversation = $this->findConversation($conversationId);
        $message = $this->findMessage($conversation, $messageId);
        if (!$message->content->canView()) {
            $this->forbidden();
        }

        return $this->renderAjax('reaction-picker', [
            'conversation' => $conversation,
            'message' => $message,
            'categories' => (new EmojiPaletteService())->categories(),
            'contentContainer' => $this->contentContainer,
        ]);
    }

    /** Toggles a voluntary emoji reaction from the current user. */
    public function actionReact(int $conversationId, int $messageId)
    {
        $this->forcePostRequest();
        $conversation = $this->findConversation($conversationId);
        $message = $this->findMessage($conversation, $messageId);
        $emoji = (string) Yii::$app->request->post('emoji');

        if (!(new EmojiPaletteService())->contains($emoji)) {
            throw new \yii\web\BadRequestHttpException('Unbekannte Emoji-Reaktion.');
        }

        (new ConversationReactionService())->toggle($message, Yii::$app->user->identity, $emoji);

        return $this->redirect($conversation->url . '#conversation-message-' . $message->id);
    }

    /** Shows the immutable before/after timeline for a changed message. */
    public function actionEditHistory(int $conversationId, int $messageId): string
    {
        $conversation = $this->findConversation($conversationId);
        $message = $this->findMessage($conversation, $messageId);
        if (!$message->content->canView()) {
            $this->forbidden();
        }

        return $this->renderAjax('edit-history', [
            'message' => $message,
            'revisions' => $message->revisions,
            'contentContainer' => $this->contentContainer,
        ]);
    }

    public function actionReceipts(int $conversationId, int $messageId): string
    {
        $conversation = $this->findConversation($conversationId);
        $message = $this->findMessage($conversation, $messageId);

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

    private function findMessage(Conversation $conversation, int $messageId): ConversationMessage
    {
        $message = ConversationMessage::find()
            ->where(['id' => $messageId, 'conversation_id' => $conversation->id])
            ->one();
        if ($message === null) {
            throw new NotFoundHttpException();
        }

        return $message;
    }
}

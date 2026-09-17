<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\conversations\models\ConversationMessageRevision;
use humhub\modules\file\models\File;
use Yii;
use yii\web\BadRequestHttpException;

final class ConversationService
{
    public function create(Conversation $conversation, ContentContainerActiveRecord $container): Conversation
    {
        if (!$conversation->content->canEdit()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        $conversation->content->container = $container;
        $conversation->content->visibility = $container->getDefaultContentVisibility();

        if (!$conversation->save()) {
            throw new BadRequestHttpException('Der Chat konnte nicht gespeichert werden.');
        }

        return $conversation;
    }

    /** Starts a separate chat from an existing message without changing Space permissions. */
    public function createSubconversation(Conversation $parent, ConversationMessage $origin, Conversation $subconversation): Conversation
    {
        if ((int) $origin->conversation_id !== (int) $parent->id || !$parent->content->canView() || !$subconversation->content->canEdit()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        $subconversation->parent_conversation_id = $parent->id;
        $subconversation->origin_message_id = $origin->id;

        return $this->create($subconversation, $parent->content->container);
    }

    /** @param string[] $fileGuids */
    public function postMessage(Conversation $conversation, ConversationMessage $message, array $fileGuids = []): ConversationMessage
    {
        if (!$conversation->content->canView() || !(new ConversationMessage($conversation->content->container))->content->canEdit()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        return Yii::$app->db->transaction(function () use ($conversation, $message, $fileGuids): ConversationMessage {
            $message->conversation_id = $conversation->id;
            $message->content->container = $conversation->content->container;
            $message->content->visibility = $conversation->content->visibility ?? Content::VISIBILITY_PRIVATE;

            if (!$message->save()) {
                throw new BadRequestHttpException('Die Nachricht konnte nicht gespeichert werden.');
            }

            $message->fileManager->attach(array_filter($fileGuids, 'is_string'));
            return $message;
        });
    }

    /**
     * Edits only the current user's own message. Space managers deliberately
     * cannot impersonate an author through this endpoint.
     */
    public function editMessage(Conversation $conversation, ConversationMessage $message): bool
    {
        if (
            (int) $message->conversation_id !== (int) $conversation->id
            || (int) $message->content->created_by !== (int) Yii::$app->user->id
            || $message->deleted_at !== null
        ) {
            throw new \yii\web\ForbiddenHttpException();
        }

        if (!$message->isAttributeChanged('message')) {
            return true;
        }

        $previousMessage = (string) $message->getOldAttribute('message');
        $editedAt = date('Y-m-d H:i:s');

        return Yii::$app->db->transaction(function () use ($message, $previousMessage, $editedAt): bool {
            $message->edited_at = $editedAt;
            if (!$message->save()) {
                return false;
            }

            $revision = new ConversationMessageRevision([
                'message_id' => $message->id,
                'editor_id' => Yii::$app->user->id,
                'previous_message' => $previousMessage,
                'revised_message' => $message->message,
                'edited_at' => $editedAt,
            ]);
            if (!$revision->save()) {
                throw new BadRequestHttpException('Der Änderungsverlauf konnte nicht gespeichert werden.');
            }

            return true;
        });
    }

    /**
     * Removes the visible message and its exclusive attachments for every
     * participant while keeping a stable timeline placeholder.
     */
    public function deleteMessage(Conversation $conversation, ConversationMessage $message): bool
    {
        if (
            (int) $message->conversation_id !== (int) $conversation->id
            || (int) $message->content->created_by !== (int) Yii::$app->user->id
            || $message->deleted_at !== null
        ) {
            throw new \yii\web\ForbiddenHttpException();
        }

        return Yii::$app->db->transaction(function () use ($message): bool {
            // Attachments belong to one conversation message; remove their data as
            // well, instead of merely hiding a link in this view.
            foreach ($message->fileManager->findAll() as $file) {
                $file->delete();
            }

            $message->message = '';
            $message->deleted_at = date('Y-m-d H:i:s');
            $message->edited_at = null;

            if (!$message->save(false, ['message', 'deleted_at', 'edited_at'])) {
                throw new BadRequestHttpException('Die Nachricht konnte nicht gelöscht werden.');
            }

            return true;
        });
    }

    public function close(Conversation $conversation, string $outcome): bool
    {
        if (!$conversation->content->canEdit()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        (new ConversationConsensusService())->start($conversation, $outcome, Yii::$app->user->identity);
        return true;
    }

    public function reopen(Conversation $conversation): bool
    {
        if (!$conversation->content->canEdit()) {
            throw new \yii\web\ForbiddenHttpException();
        }

        $conversation->closed_at = null;
        return $conversation->save(false, ['closed_at']);
    }
}

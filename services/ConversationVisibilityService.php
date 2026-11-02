<?php
// SPDX-License-Identifier: AGPL-3.0-only
namespace humhub\modules\conversations\services;

use humhub\modules\content\models\Content;
use humhub\modules\content\permissions\CreatePublicContent;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\space\models\Space;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

final class ConversationVisibilityService
{
    public function labels(Conversation $conversation): array
    {
        $circle = $conversation->content->container instanceof Space
            && (new ConversationMentionService())->isCircle($conversation->content->container);
        return [
            Content::VISIBILITY_PRIVATE => $circle ? 'Nur Kreismitglieder' : 'Nur Space-Mitglieder',
            Content::VISIBILITY_PUBLIC => $circle ? 'Alle (auch außerhalb des Kreises)' : 'Alle (auch außerhalb des Spaces)',
        ];
    }

    public function canShare(Conversation $conversation): bool
    {
        $space = $conversation->content->container;
        return $space instanceof Space && (int) $space->visibility !== Space::VISIBILITY_NONE
            && $space->can(CreatePublicContent::class);
    }

    public function change(Conversation $conversation, int $visibility): void
    {
        if (Yii::$app->user->isGuest || !$conversation->content->canEdit()) { throw new ForbiddenHttpException(); }
        if (!in_array($visibility, [Content::VISIBILITY_PRIVATE, Content::VISIBILITY_PUBLIC], true)) {
            throw new BadRequestHttpException('Ungültige Sichtbarkeit.');
        }
        if ($visibility === Content::VISIBILITY_PUBLIC && !$this->canShare($conversation)) {
            throw new ForbiddenHttpException('Dieser Space ist privat oder du darfst hier keine öffentlichen Inhalte freigeben.');
        }
        Yii::$app->db->transaction(function () use ($conversation, $visibility): void {
            Yii::$app->db->createCommand()->update(Conversation::tableName(),
                ['id' => new \yii\db\Expression('[[id]]')], ['id' => $conversation->id])->execute();
            $conversation->content->refresh();
            if ((int) $conversation->content->visibility === $visibility) { return; }
            $conversation->content->visibility = $visibility;
            if (!$conversation->content->save() || (int) $conversation->content->visibility !== $visibility) {
                throw new BadRequestHttpException('Die Sichtbarkeit konnte nicht gespeichert werden.');
            }
            // The Content event also covers HumHub's native visibility menu.
        });
    }

    public function synchronizeMessages(Conversation $conversation, int $visibility): void
    {
        Yii::$app->db->transaction(function () use ($conversation, $visibility): void {
            Yii::$app->db->createCommand()->update(Conversation::tableName(),
                ['id' => new \yii\db\Expression('[[id]]')], ['id' => $conversation->id])->execute();
            foreach (ConversationMessage::find()->where(['conversation_id' => $conversation->id])->each(100) as $message) {
                if ((int) $message->content->visibility === $visibility) { continue; }
                $message->content->visibility = $visibility;
                if (!$message->content->save() || (int) $message->content->visibility !== $visibility) {
                    throw new BadRequestHttpException('Die Sichtbarkeit der Chat-Nachrichten konnte nicht aktualisiert werden.');
                }
            }
        });
    }
}

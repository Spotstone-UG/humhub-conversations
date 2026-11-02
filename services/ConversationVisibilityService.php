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
            0 => $circle ? 'Unsichtbar für Außenstehende – nur Kreismitglieder' : 'Unsichtbar für Außenstehende – nur Space-Mitglieder',
            1 => 'Öffentlich lesbar',
            2 => 'Öffentlich les- und schreibbar',
        ];
    }

    public function mode(Conversation $conversation): int
    {
        return !$conversation->content->isPublic() ? 0 : ((int) $conversation->outsider_writable === 1 ? 2 : 1);
    }

    public function canParticipate(Conversation $conversation, ?\humhub\modules\user\models\User $user = null): bool
    {
        if (!$conversation->isNewRecord) {
            $conversation = Conversation::findOne($conversation->id);
            if ($conversation === null) { return false; }
        }
        $user ??= Yii::$app->user->identity;
        if (!$user || !$user->isActive() || !$conversation->content->canView($user)) { return false; }
        $space = $conversation->content->container;
        if (!$space instanceof Space || $space->isBlockedForUser($user)) { return false; }
        if ($user->canManageAllContent()) { return true; }
        if ($space->isMember($user->id)) {
            return $space->getPermissionManager($user)->can(\humhub\modules\conversations\permissions\CreateConversation::class);
        }
        return (int) $space->visibility !== Space::VISIBILITY_NONE && $this->mode($conversation) === 2;
    }

    public function canWrite(Conversation $conversation, ?\humhub\modules\user\models\User $user = null): bool
    {
        if (!$conversation->isNewRecord) {
            $conversation = Conversation::findOne($conversation->id);
            if ($conversation === null) { return false; }
        }
        return !$conversation->isClosed && $this->canParticipate($conversation, $user);
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
        if (!in_array($visibility, [0, 1, 2], true)) {
            throw new BadRequestHttpException('Ungültige Sichtbarkeit.');
        }
        if ($visibility > 0 && !$this->canShare($conversation)) {
            throw new ForbiddenHttpException('Dieser Space ist privat oder du darfst hier keine öffentlichen Inhalte freigeben.');
        }
        Yii::$app->db->transaction(function () use ($conversation, $visibility): void {
            Yii::$app->db->createCommand()->update(Conversation::tableName(),
                ['id' => new \yii\db\Expression('[[id]]')], ['id' => $conversation->id])->execute();
            $conversation->content->refresh();
            $conversation->refresh();
            if ($this->mode($conversation) === $visibility) { return; }
            $contentVisibility = $visibility === 0 ? Content::VISIBILITY_PRIVATE : Content::VISIBILITY_PUBLIC;
            $oldVisibility = (int) $conversation->content->visibility;
            $conversation->outsider_writable = $visibility === 2 ? 1 : 0;
            $conversation->content->visibility = $contentVisibility;
            if (!$conversation->save(false, ['outsider_writable']) || (int) $conversation->content->visibility !== $contentVisibility) {
                throw new BadRequestHttpException('Die Sichtbarkeit konnte nicht gespeichert werden.');
            }
            if ($oldVisibility === $contentVisibility) {
                (new ConversationNotifier())->notifyChanged($conversation, Yii::$app->user->identity);
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

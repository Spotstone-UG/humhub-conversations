<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\content\widgets\richtext\extensions\mentioning\MentioningExtension;
use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\Mentioning;
use humhub\modules\user\models\User;
use humhub\modules\user\permissions\CanMention;
use humhub\modules\user\notifications\Mentioned;
use Yii;

final class ConversationMentionService
{
    public function isCircle(Space $space): bool
    {
        $circleClass = 'humhub\\modules\\sociocraticGovernance\\models\\Circle';
        return Yii::$app->hasModule('sociocratic-governance')
            && class_exists($circleClass)
            && $circleClass::find()->where(['space_id' => $space->id])->exists();
    }

    public function candidates(Space $space)
    {
        return $this->isCircle($space)
            ? User::find()->available()
            : $space->getMemberListService()->getAvailableQuery();
    }

    public function canMention(Space $space, User $user): bool
    {
        return $this->candidates($space)->andWhere(['user.id' => $user->id])->exists()
            && $user->permissionManager->can(CanMention::class);
    }

    /** Reject forged/out-of-scope mentions as well as restricting autocomplete. */
    public function validate(ConversationMessage $message): void
    {
        $space = $message->content->container;
        foreach (MentioningExtension::scanLinkExtension($message->message, 'mention') as $match) {
            $user = User::findOne(['guid' => $match->getExtensionId()]);
            if (!$space instanceof Space || !$user || !$this->canMention($space, $user)) {
                $message->addError('message', 'Diese Person kann in diesem Space nicht erwähnt werden.');
            }
        }
    }

    /** One native mention notice per person/chat; message records retain the @ indicator. */
    public function process(ConversationMessage $message): array
    {
        if ($message->deleted_at !== null || !$message->content->getStateService()->isPublished()) {
            return [];
        }
        $conversation = $message->conversation;
        $space = $conversation->content->container;
        $matches = MentioningExtension::scanLinkExtension($message->message, 'mention');
        if ($matches === []) {
            return [];
        }
        $newUsers = [];
        Yii::$app->db->transaction(function () use ($message, $conversation, $space, $matches, &$newUsers): void {
            // Serialize first mentions in the same chat, including simultaneous messages.
            Yii::$app->db->createCommand()->update(Conversation::tableName(),
                ['id' => new \yii\db\Expression('[[id]]')], ['id' => $conversation->id])->execute();
            foreach ($matches as $match) {
                $user = User::findOne(['guid' => $match->getExtensionId()]);
                if (!$space instanceof Space || !$user || !$this->canMention($space, $user)
                    || !$conversation->content->canView($user) || !$message->content->canView($user)) {
                    continue;
                }
                $key = ['object_model' => ConversationMessage::class, 'object_id' => $message->id, 'user_id' => $user->id];
                if (!$this->mentionExists($key)) {
                    // The chat mention below owns the notification; avoid another notice per message.
                    Yii::$app->db->createCommand()->insert(Mentioning::tableName(), $key)->execute();
                }
                $chatKey = ['object_model' => Conversation::class, 'object_id' => $conversation->id, 'user_id' => $user->id];
                if (!$this->mentionExists($chatKey)) {
                    Yii::$app->db->createCommand()->insert(Mentioning::tableName(), $chatKey)->execute();
                    $conversation->follow($user->id);
                    Yii::$app->db->createCommand()->upsert('conversation_interest', [
                        'conversation_id' => $conversation->id,
                        'user_id' => $user->id,
                        'interested_at' => date('Y-m-d H:i:s'),
                    ], false)->execute();
                    $sender = $message->content->updatedBy ?? $message->content->createdBy;
                    if ($sender !== null) {
                        Mentioned::instance()->from($sender)->about($message)->send($user);
                    }
                    $newUsers[] = $user;
                }
            }
        });
        return $newUsers;
    }

    private function mentionExists(array $key): bool
    {
        $command = Mentioning::find()->select('id')->where($key)->createCommand();
        // A current read is required after waiting for the chat lock under
        // MySQL REPEATABLE READ; an earlier transaction snapshot may be stale.
        $suffix = in_array(Yii::$app->db->driverName, ['mysql', 'pgsql'], true) ? ' FOR UPDATE' : '';
        return Yii::$app->db->createCommand($command->getRawSql() . $suffix)->queryScalar() !== false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use yii\db\Expression;

/** Builds the personal, permission-aware overview shared by the main menu and its page. */
final class ConversationOverviewService
{
    /**
     * @return array<int, array{space: Space, conversations: Conversation[], unreadCounts: array<int, int>}>
     */
    public function groupedBySpace(User $user): array
    {
        $all = Conversation::find()
            ->readable($user)
            ->orderBy(['conversation.last_message_at' => SORT_DESC, 'conversation.id' => SORT_DESC])
            ->all();

        $groups = [];
        foreach ($all as $conversation) {
            $space = $conversation->content->container;
            if (!$space instanceof Space) {
                continue;
            }

            if (!isset($groups[$space->id])) {
                $groups[$space->id] = ['space' => $space, 'items' => []];
            }
            $groups[$space->id]['items'][] = $conversation;
        }

        uasort($groups, static fn(array $left, array $right): int => strnatcasecmp($left['space']->displayName, $right['space']->displayName));

        foreach ($groups as &$group) {
            $topLevel = [];
            $children = [];
            foreach ($group['items'] as $conversation) {
                if ($conversation->parent_conversation_id === null) {
                    $topLevel[] = $conversation;
                } else {
                    $children[(int) $conversation->parent_conversation_id][] = $conversation;
                }
            }

            $ordered = [];
            foreach ($topLevel as $parent) {
                $ordered[] = $parent;
                foreach ($children[(int) $parent->id] ?? [] as $child) {
                    $ordered[] = $child;
                }
            }
            // A deleted parent must not make a still-readable child disappear.
            foreach ($children as $parentId => $orphanedChildren) {
                if (!array_filter($topLevel, static fn(Conversation $item): bool => (int) $item->id === $parentId)) {
                    foreach ($orphanedChildren as $child) {
                        $ordered[] = $child;
                    }
                }
            }

            $group['conversations'] = $ordered;
            $group['unreadCounts'] = $this->unreadCounts($ordered, $user);
            unset($group['items']);
        }
        unset($group);

        return $groups;
    }

    public function unreadTotal(User $user): int
    {
        $total = 0;
        foreach ($this->groupedBySpace($user) as $group) {
            $total += array_sum($group['unreadCounts']);
        }

        return $total;
    }

    /** @param Conversation[] $conversations @return array<int, int> */
    private function unreadCounts(array $conversations, User $user): array
    {
        $ids = array_map(static fn(Conversation $conversation): int => (int) $conversation->id, $conversations);
        if ($ids === []) {
            return [];
        }

        $rows = ConversationMessage::find()
            ->select([
                'conversation_id' => 'conversation_message.conversation_id',
                'unread_count' => new Expression('COUNT(*)'),
            ])
            ->leftJoin('{{%conversation_user_state}} state', 'state.conversation_id = conversation_message.conversation_id AND state.user_id = :userId', [':userId' => $user->id])
            ->where(['conversation_message.conversation_id' => $ids])
            ->andWhere('conversation_message.id > COALESCE(state.last_seen_message_id, 0)')
            ->groupBy(['conversation_message.conversation_id'])
            ->asArray()
            ->all();

        $counts = array_fill_keys($ids, 0);
        foreach ($rows as $row) {
            $counts[(int) $row['conversation_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }
}

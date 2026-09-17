<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_200000_add_subconversations_and_lifecycle extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation}}', 'parent_conversation_id', $this->integer());
        $this->addColumn('{{%conversation}}', 'origin_message_id', $this->integer());
        $this->addColumn('{{%conversation}}', 'closed_at', $this->dateTime());
        $this->addColumn('{{%conversation}}', 'outcome', $this->text());
        $this->createIndex('idx_conversation_parent', '{{%conversation}}', ['parent_conversation_id', 'id']);
        $this->addForeignKey('fk_conversation_parent', '{{%conversation}}', 'parent_conversation_id', '{{%conversation}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_conversation_origin_message', '{{%conversation}}', 'origin_message_id', '{{%conversation_message}}', 'id', 'SET NULL');

        $this->addColumn('{{%conversation_message}}', 'deleted_at', $this->dateTime());
        $this->createIndex('idx_conversation_message_deleted', '{{%conversation_message}}', ['conversation_id', 'deleted_at']);
    }

    public function safeDown(): bool
    {
        echo "Conversation history is intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_180000_add_message_revision_history extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%conversation_message_revision}}', [
            'id' => $this->primaryKey(),
            'message_id' => $this->integer()->notNull(),
            'editor_id' => $this->integer()->notNull(),
            'previous_message' => $this->text()->notNull(),
            'revised_message' => $this->text()->notNull(),
            'edited_at' => $this->dateTime()->notNull(),
            'FOREIGN KEY ([[message_id]]) REFERENCES {{%conversation_message}} ([[id]]) ON DELETE CASCADE',
            'FOREIGN KEY ([[editor_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->createIndex('idx_conversation_message_revision_message', '{{%conversation_message_revision}}', ['message_id', 'edited_at']);
    }

    public function safeDown(): bool
    {
        echo "Message revision history is intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_150000_initial extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%conversation}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'summary' => $this->text(),
            'last_message_at' => $this->dateTime(),
        ]);

        $this->createTable('{{%conversation_message}}', [
            'id' => $this->primaryKey(),
            'conversation_id' => $this->integer()->notNull(),
            'message' => $this->text()->notNull(),
            'FOREIGN KEY ([[conversation_id]]) REFERENCES {{%conversation}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->createIndex('idx_conversation_message_conversation', '{{%conversation_message}}', ['conversation_id', 'id']);

        $this->createTable('{{%conversation_user_state}}', [
            'conversation_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'last_seen_message_id' => $this->integer(),
            'last_opened_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[conversation_id]], [[user_id]])',
            'FOREIGN KEY ([[conversation_id]]) REFERENCES {{%conversation}} ([[id]]) ON DELETE CASCADE',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);

        $this->createTable('{{%conversation_read_receipt}}', [
            'message_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'read_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[message_id]], [[user_id]])',
            'FOREIGN KEY ([[message_id]]) REFERENCES {{%conversation_message}} ([[id]]) ON DELETE CASCADE',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->createIndex('idx_conversation_receipt_message_read_at', '{{%conversation_read_receipt}}', ['message_id', 'read_at']);

        $this->createTable('{{%conversation_user_setting}}', [
            'user_id' => $this->integer()->notNull(),
            'read_receipts_enabled' => $this->boolean()->notNull()->defaultValue(true),
            'PRIMARY KEY ([[user_id]])',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
    }

    public function safeDown(): bool
    {
        echo "Conversation data is intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}


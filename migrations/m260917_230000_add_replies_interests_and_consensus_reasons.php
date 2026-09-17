<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_230000_add_replies_interests_and_consensus_reasons extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation_message}}', 'reply_to_message_id', $this->integer());
        $this->createIndex('idx_conversation_message_reply', '{{%conversation_message}}', 'reply_to_message_id');
        $this->addForeignKey('fk_conversation_message_reply', '{{%conversation_message}}', 'reply_to_message_id', '{{%conversation_message}}', 'id', 'SET NULL');

        $this->createTable('{{%conversation_interest}}', [
            'conversation_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'interested_at' => $this->dateTime()->notNull(),
            'PRIMARY KEY ([[conversation_id]], [[user_id]])',
            'FOREIGN KEY ([[conversation_id]]) REFERENCES {{%conversation}} ([[id]]) ON DELETE CASCADE',
            'FOREIGN KEY ([[user_id]]) REFERENCES {{%user}} ([[id]]) ON DELETE CASCADE',
        ]);
        $this->createIndex('idx_conversation_interest_user', '{{%conversation_interest}}', ['user_id', 'interested_at']);

        $this->addColumn('{{%conversation_consensus_response}}', 'reason', $this->text());
    }

    public function safeDown(): bool
    {
        echo "Conversation replies, interests and consensus history are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

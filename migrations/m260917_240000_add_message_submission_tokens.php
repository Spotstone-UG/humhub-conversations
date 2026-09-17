<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Makes one client send action map to no more than one persisted message. */
final class m260917_240000_add_message_submission_tokens extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%conversation_message_submission}}', [
            'id' => $this->primaryKey(),
            'conversation_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'submission_token' => $this->string(64)->notNull(),
            'message_id' => $this->integer(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('ux_conversation_message_submission_token', '{{%conversation_message_submission}}', ['conversation_id', 'user_id', 'submission_token'], true);
        $this->createIndex('idx_conversation_message_submission_message', '{{%conversation_message_submission}}', 'message_id');
        $this->addForeignKey('fk_conversation_message_submission_conversation', '{{%conversation_message_submission}}', 'conversation_id', '{{%conversation}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_conversation_message_submission_user', '{{%conversation_message_submission}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_conversation_message_submission_message', '{{%conversation_message_submission}}', 'message_id', '{{%conversation_message}}', 'id', 'CASCADE');
    }

    public function safeDown(): bool
    {
        echo "Message submission claims are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_220000_add_muted_spaces extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%conversation_muted_space}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'space_id' => $this->integer()->notNull(),
            'muted_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('uniq_conversation_muted_space', '{{%conversation_muted_space}}', ['user_id', 'space_id'], true);
        $this->addForeignKey('fk_conversation_muted_space_user', '{{%conversation_muted_space}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_conversation_muted_space_space', '{{%conversation_muted_space}}', 'space_id', '{{%space}}', 'id', 'CASCADE');
    }

    public function safeDown(): bool
    {
        echo "Persönliche Stummschaltungen bleiben erhalten. Stelle für einen Rückbau ein Backup wieder her.\n";
        return false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_210000_add_consensus_workflow extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation}}', 'closed_by', $this->integer());
        $this->createIndex('idx_conversation_closed_by', '{{%conversation}}', 'closed_by');
        $this->addForeignKey('fk_conversation_closed_by', '{{%conversation}}', 'closed_by', '{{%user}}', 'id', 'SET NULL');

        $this->createTable('{{%conversation_consensus_proposal}}', [
            'id' => $this->primaryKey(),
            'conversation_id' => $this->integer()->notNull(),
            'body' => $this->text()->notNull(),
            'created_by' => $this->integer()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'supersedes_proposal_id' => $this->integer(),
        ]);
        $this->createIndex('idx_consensus_proposal_conversation', '{{%conversation_consensus_proposal}}', ['conversation_id', 'created_at']);
        $this->addForeignKey('fk_consensus_proposal_conversation', '{{%conversation_consensus_proposal}}', 'conversation_id', '{{%conversation}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_consensus_proposal_creator', '{{%conversation_consensus_proposal}}', 'created_by', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_consensus_proposal_supersedes', '{{%conversation_consensus_proposal}}', 'supersedes_proposal_id', '{{%conversation_consensus_proposal}}', 'id', 'SET NULL');

        $this->createTable('{{%conversation_consensus_response}}', [
            'id' => $this->primaryKey(),
            'proposal_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'decision' => $this->string(16)->notNull(),
            'responded_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex('uniq_consensus_response', '{{%conversation_consensus_response}}', ['proposal_id', 'user_id'], true);
        $this->addForeignKey('fk_consensus_response_proposal', '{{%conversation_consensus_response}}', 'proposal_id', '{{%conversation_consensus_proposal}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_consensus_response_user', '{{%conversation_consensus_response}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        // Existing conclusions remain readable and get a first consensus version.
        $model = addslashes(\humhub\modules\conversations\models\Conversation::class);
        $this->execute("UPDATE {{%conversation}} c JOIN {{%content}} content ON content.object_id = c.id AND content.object_model = '{$model}' SET c.closed_by = content.updated_by WHERE c.closed_at IS NOT NULL AND c.closed_by IS NULL");
        $this->execute("INSERT INTO {{%conversation_consensus_proposal}} (conversation_id, body, created_by, created_at) SELECT c.id, c.outcome, COALESCE(c.closed_by, content.created_by), c.closed_at FROM {{%conversation}} c JOIN {{%content}} content ON content.object_id = c.id AND content.object_model = '{$model}' WHERE c.closed_at IS NOT NULL AND c.outcome IS NOT NULL AND c.outcome <> ''");
    }

    public function safeDown(): bool
    {
        echo "Konsensverläufe bleiben aus Gründen der Nachvollziehbarkeit erhalten. Stelle für einen Rückbau ein Backup wieder her.\n";
        return false;
    }
}

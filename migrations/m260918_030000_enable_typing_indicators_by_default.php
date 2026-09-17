<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Enables typing indicators by default while keeping the account preference available. */
final class m260918_030000_enable_typing_indicators_by_default extends Migration
{
    public function safeUp(): void
    {
        $this->alterColumn(
            '{{%conversation_user_setting}}',
            'typing_indicators_enabled',
            $this->boolean()->notNull()->defaultValue(true)
        );
        $this->update('{{%conversation_user_setting}}', ['typing_indicators_enabled' => true]);
    }

    public function safeDown(): bool
    {
        echo "Typing indicator preferences are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

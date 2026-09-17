<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Stores each person's global Conversations composer shortcut preference. */
final class m260918_010000_add_composer_send_shortcut extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation_user_setting}}', 'send_with_ctrl_enter', $this->boolean()->notNull()->defaultValue(false));
    }

    public function safeDown(): bool
    {
        echo "Composer shortcut preferences are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Typing visibility is opt-in and deliberately defaults to disabled. */
final class m260918_020000_add_typing_indicator_setting extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation_user_setting}}', 'typing_indicators_enabled', $this->boolean()->notNull()->defaultValue(false));
    }

    public function safeDown(): bool
    {
        echo "Typing indicator preferences are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

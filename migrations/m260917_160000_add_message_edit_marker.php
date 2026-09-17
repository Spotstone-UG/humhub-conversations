<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

final class m260917_160000_add_message_edit_marker extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation_message}}', 'edited_at', $this->dateTime()->null());
    }

    public function safeDown(): bool
    {
        echo "Edited-message markers are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Preserve native keyboard emoji in messages and their complete edit history. */
final class m261109_140000_upgrade_message_unicode_storage extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $this->execute('ALTER TABLE {{%conversation_message}} MODIFY [[message]] TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        $this->execute('ALTER TABLE {{%conversation_message_revision}} MODIFY [[previous_message]] TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, MODIFY [[revised_message]] TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }

    public function safeDown(): bool
    {
        echo "Unicode messages and edit history are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

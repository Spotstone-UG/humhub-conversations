<?php
// SPDX-License-Identifier: AGPL-3.0-only

use yii\db\Migration;

/** Ensures existing MySQL installations can store and compare all Unicode emoji. */
final class m260918_040000_upgrade_emoji_reaction_storage extends Migration
{
    public function safeUp(): void
    {
        if ($this->db->driverName !== 'mysql') {
            return;
        }

        $this->execute('ALTER TABLE {{%conversation_reaction}} MODIFY [[emoji]] VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
        $this->execute('ALTER TABLE {{%post_emoji_reaction}} MODIFY [[emoji]] VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    public function safeDown(): bool
    {
        echo "Emoji reactions are intentionally retained. Restore a coordinated database backup to roll back.\n";
        return false;
    }
}

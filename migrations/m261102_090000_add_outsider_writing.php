<?php
// SPDX-License-Identifier: AGPL-3.0-only
use yii\db\Migration;
final class m261102_090000_add_outsider_writing extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn('{{%conversation}}', 'outsider_writable', $this->smallInteger()->notNull()->defaultValue(0));
    }
    public function safeDown(): bool { return false; }
}

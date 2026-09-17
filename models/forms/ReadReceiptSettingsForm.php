<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models\forms;

use yii\base\Model;

final class ReadReceiptSettingsForm extends Model
{
    public bool $readReceiptsEnabled = true;

    public function rules(): array
    {
        return [['readReceiptsEnabled', 'boolean']];
    }
}


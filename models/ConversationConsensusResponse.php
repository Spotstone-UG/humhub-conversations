<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\user\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** One participant's current position on one result proposal. */
final class ConversationConsensusResponse extends ActiveRecord
{
    public const DECISION_CONSENT = 'consent';
    public const DECISION_OBJECTION = 'objection';

    public static function tableName(): string
    {
        return 'conversation_consensus_response';
    }

    public function rules(): array
    {
        return [
            [['proposal_id', 'user_id', 'decision'], 'required'],
            [['proposal_id', 'user_id'], 'integer', 'min' => 1],
            [['decision'], 'in', 'range' => [self::DECISION_CONSENT, self::DECISION_OBJECTION]],
            [['reason'], 'string', 'max' => 2000],
            [['responded_at'], 'safe'],
        ];
    }

    public function getProposal(): ActiveQuery
    {
        return $this->hasOne(ConversationConsensusProposal::class, ['id' => 'proposal_id']);
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}

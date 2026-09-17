<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\models;

use humhub\modules\user\models\User;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** A versioned result proposal that participants can confirm or object to. */
final class ConversationConsensusProposal extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'conversation_consensus_proposal';
    }

    public function rules(): array
    {
        return [
            [['conversation_id', 'body', 'created_by'], 'required'],
            [['conversation_id', 'created_by', 'supersedes_proposal_id'], 'integer', 'min' => 1],
            [['body'], 'string', 'max' => 4000],
            [['created_at'], 'safe'],
        ];
    }

    public function getConversation(): ActiveQuery
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversation_id']);
    }

    public function getCreatedBy(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    public function getResponses(): ActiveQuery
    {
        return $this->hasMany(ConversationConsensusResponse::class, ['proposal_id' => 'id']);
    }
}

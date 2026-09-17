<?php
// SPDX-License-Identifier: AGPL-3.0-only

namespace humhub\modules\conversations\services;

use humhub\modules\conversations\models\Conversation;
use humhub\modules\conversations\models\ConversationConsensusProposal;
use humhub\modules\conversations\models\ConversationConsensusResponse;
use humhub\modules\conversations\models\ConversationMessage;
use humhub\modules\user\models\User;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/** Keeps the consensus display transparent without applying any external consequence. */
final class ConversationConsensusService
{
    public function start(Conversation $conversation, string $outcome, User $author): ConversationConsensusProposal
    {
        return Yii::$app->db->transaction(function () use ($conversation, $outcome, $author): ConversationConsensusProposal {
            $conversation->outcome = $outcome;
            $conversation->closed_at = date('Y-m-d H:i:s');
            $conversation->closed_by = $author->id;
            if (!$conversation->save()) {
                throw new BadRequestHttpException('Der Chat konnte nicht beendet werden.');
            }

            return $this->createProposal($conversation, $outcome, $author);
        });
    }

    /** @return array{proposal: ConversationConsensusProposal|null, participants: User[], responses: array<int, string>, status: string, consentCount: int, objectionCount: int, deadline: string|null, canRespond: bool, canProposeAlternative: bool} */
    public function currentStatus(Conversation $conversation, ?User $currentUser): array
    {
        $proposal = $conversation->getConsensusProposals()->one();
        $participants = $this->participants($conversation);
        $participantIds = array_map(static fn(User $user): int => (int) $user->id, $participants);
        $responses = [];
        if ($proposal !== null) {
            foreach ($proposal->responses as $response) {
                $responses[(int) $response->user_id] = $response->decision;
            }
        }

        $consentCount = count(array_filter($responses, static fn(string $decision): bool => $decision === ConversationConsensusResponse::DECISION_CONSENT));
        $objectionCount = count(array_filter($responses, static fn(string $decision): bool => $decision === ConversationConsensusResponse::DECISION_OBJECTION));
        $deadline = $proposal === null ? null : date('Y-m-d H:i:s', strtotime($proposal->created_at . ' +14 days'));
        $allConsented = $participantIds !== [] && count(array_intersect($participantIds, array_keys(array_filter($responses, static fn(string $decision): bool => $decision === ConversationConsensusResponse::DECISION_CONSENT)))) === count($participantIds);

        $status = 'open';
        if ($proposal === null) {
            $status = 'not_started';
        } elseif ($objectionCount > 0) {
            $status = 'objection';
        } elseif ($allConsented) {
            $status = 'confirmed_all';
        } elseif (strtotime($deadline) <= time()) {
            $status = 'confirmed_timeout';
        }

        $isParticipant = $currentUser !== null && in_array((int) $currentUser->id, $participantIds, true);
        return [
            'proposal' => $proposal,
            'participants' => $participants,
            'responses' => $responses,
            'status' => $status,
            'consentCount' => $consentCount,
            'objectionCount' => $objectionCount,
            'deadline' => $deadline,
            'canRespond' => $isParticipant && $proposal !== null && !in_array($status, ['confirmed_all', 'confirmed_timeout'], true),
            'canProposeAlternative' => $isParticipant && $proposal !== null && $status === 'objection',
        ];
    }

    public function respond(Conversation $conversation, ConversationConsensusProposal $proposal, User $user, string $decision): void
    {
        $status = $this->currentStatus($conversation, $user);
        if ($status['proposal'] === null || (int) $status['proposal']->id !== (int) $proposal->id || !$status['canRespond']) {
            throw new ForbiddenHttpException();
        }
        if (!in_array($decision, [ConversationConsensusResponse::DECISION_CONSENT, ConversationConsensusResponse::DECISION_OBJECTION], true)) {
            throw new BadRequestHttpException('Ungültige Konsensentscheidung.');
        }

        Yii::$app->db->createCommand()->upsert('{{%conversation_consensus_response}}', [
            'proposal_id' => $proposal->id,
            'user_id' => $user->id,
            'decision' => $decision,
            'responded_at' => date('Y-m-d H:i:s'),
        ], [
            'decision' => $decision,
            'responded_at' => date('Y-m-d H:i:s'),
        ])->execute();
    }

    public function proposeAlternative(Conversation $conversation, ConversationConsensusProposal $proposal, User $user, string $body): ConversationConsensusProposal
    {
        $status = $this->currentStatus($conversation, $user);
        if ($status['proposal'] === null || (int) $status['proposal']->id !== (int) $proposal->id || !$status['canProposeAlternative']) {
            throw new ForbiddenHttpException();
        }
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 4000) {
            throw new BadRequestHttpException('Der Alternativvorschlag muss zwischen 1 und 4000 Zeichen lang sein.');
        }

        return Yii::$app->db->transaction(function () use ($conversation, $proposal, $user, $body): ConversationConsensusProposal {
            $conversation->outcome = $body;
            if (!$conversation->save(false, ['outcome'])) {
                throw new BadRequestHttpException('Der Alternativvorschlag konnte nicht gespeichert werden.');
            }
            return $this->createProposal($conversation, $body, $user, $proposal->id);
        });
    }

    /** @return User[] */
    private function participants(Conversation $conversation): array
    {
        $ids = ConversationMessage::find()
            ->select('content.created_by')
            ->joinWith('content')
            ->where(['conversation_message.conversation_id' => $conversation->id])
            ->andWhere(['IS NOT', 'content.created_by', null])
            ->distinct()
            ->column();
        $ids[] = $conversation->content->created_by;
        if ($conversation->closed_by !== null) {
            $ids[] = $conversation->closed_by;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        return User::find()->where(['id' => $ids, 'status' => User::STATUS_ENABLED])->orderBy(['id' => SORT_ASC])->all();
    }

    private function createProposal(Conversation $conversation, string $body, User $author, ?int $supersedesProposalId = null): ConversationConsensusProposal
    {
        $proposal = new ConversationConsensusProposal([
            'conversation_id' => $conversation->id,
            'body' => $body,
            'created_by' => $author->id,
            'supersedes_proposal_id' => $supersedesProposalId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$proposal->save()) {
            throw new BadRequestHttpException('Der Konsensvorschlag konnte nicht gespeichert werden.');
        }
        return $proposal;
    }
}

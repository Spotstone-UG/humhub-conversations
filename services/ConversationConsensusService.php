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
        if (!$this->isParticipant($conversation, $author)) {
            throw new ForbiddenHttpException('Nur Teilnehmende können einen Chat beenden.');
        }

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

    /** A participant has made a word contribution in this exact chat. Reactions never count. */
    public function isParticipant(Conversation $conversation, User $user): bool
    {
        foreach ($this->participants($conversation) as $participant) {
            if ((int) $participant->id === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    /** @return array{proposal: ConversationConsensusProposal|null, history: ConversationConsensusProposal[], participants: User[], participantResponses: array<int, array{user: User, decision: string|null, respondedAt: string|null, reason: string|null}>, responses: array<int, string>, status: string, consentCount: int, objectionCount: int, deadline: string|null, canRespond: bool, canWithdrawObjection: bool, canProposeAlternative: bool} */
    public function currentStatus(Conversation $conversation, ?User $currentUser): array
    {
        $history = $conversation->getConsensusProposals()->with(['createdBy', 'responses.user'])->all();
        $proposal = $history[0] ?? null;
        $participants = $this->participants($conversation);
        $participantIds = array_map(static fn(User $user): int => (int) $user->id, $participants);
        $responses = [];
        $responseModels = [];
        if ($proposal !== null) {
            foreach ($proposal->responses as $response) {
                $responses[(int) $response->user_id] = $response->decision;
                $responseModels[(int) $response->user_id] = $response;
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
        $participantResponses = [];
        foreach ($participants as $participant) {
            $response = $responseModels[(int) $participant->id] ?? null;
            $participantResponses[] = [
                'user' => $participant,
                'decision' => $response?->decision,
                'respondedAt' => $response?->responded_at,
                'reason' => $response?->reason,
            ];
        }
        $canWithdrawObjection = $isParticipant
            && $proposal !== null
            && $status === 'objection'
            && ($responses[(int) $currentUser->id] ?? null) === ConversationConsensusResponse::DECISION_OBJECTION;
        return [
            'proposal' => $proposal,
            'history' => $history,
            'participants' => $participants,
            'participantResponses' => $participantResponses,
            'responses' => $responses,
            'status' => $status,
            'consentCount' => $consentCount,
            'objectionCount' => $objectionCount,
            'deadline' => $deadline,
            'canRespond' => $isParticipant && $proposal !== null && !in_array($status, ['confirmed_all', 'confirmed_timeout'], true),
            'canWithdrawObjection' => $canWithdrawObjection,
            'canProposeAlternative' => $isParticipant && $proposal !== null && $status === 'objection',
        ];
    }

    public function respond(Conversation $conversation, ConversationConsensusProposal $proposal, User $user, string $decision, ?string $reason = null): void
    {
        $status = $this->currentStatus($conversation, $user);
        $withdrawsOwnObjection = $status['canWithdrawObjection'] && $decision === ConversationConsensusResponse::DECISION_CONSENT;
        if ($status['proposal'] === null || (int) $status['proposal']->id !== (int) $proposal->id || (!$status['canRespond'] && !$withdrawsOwnObjection)) {
            throw new ForbiddenHttpException();
        }
        if (!in_array($decision, [ConversationConsensusResponse::DECISION_CONSENT, ConversationConsensusResponse::DECISION_OBJECTION], true)) {
            throw new BadRequestHttpException('Ungültige Konsensentscheidung.');
        }
        $reason = trim((string) $reason);
        if ($decision === ConversationConsensusResponse::DECISION_OBJECTION && $reason === '') {
            throw new BadRequestHttpException('Ein Widerspruch braucht eine kurze Begründung.');
        }
        if (mb_strlen($reason) > 2000) {
            throw new BadRequestHttpException('Die Begründung darf höchstens 2000 Zeichen lang sein.');
        }

        Yii::$app->db->createCommand()->upsert('{{%conversation_consensus_response}}', [
            'proposal_id' => $proposal->id,
            'user_id' => $user->id,
            'decision' => $decision,
            'reason' => $decision === ConversationConsensusResponse::DECISION_OBJECTION ? $reason : null,
            'responded_at' => date('Y-m-d H:i:s'),
        ], [
            'decision' => $decision,
            'reason' => $decision === ConversationConsensusResponse::DECISION_OBJECTION ? $reason : null,
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

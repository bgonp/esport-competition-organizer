<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PlayerRepository;
use App\Repository\RegistrationRepository;
use App\Repository\RoundRepository;
use App\Repository\TeamRepository;
use App\Service\CompetitionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/** @Route("/api") */
class ApiController
{
    /** @Route("/set_round_winner", name="api_winner", methods={"PUT"}) */
    public function setRoundWinner(
        Request $request,
        CompetitionService $competitionService,
        RoundRepository $roundRepository
    ): JsonResponse {
        $roundId = $request->get('round_id');
        $round = $roundRepository->find($roundId);
        $teamId = $request->get('team_id');
        $affectedRound = null;
        $response = [
            'origin' => [
                'round_id' => $round->getId(),
                'teams' => [],
                'winner' => false,
            ]
        ];
        foreach ($round->getTeams() as $team) {
            $response['origin']['teams'][] = $team->getId();
            if ($team->getId() == $teamId) {
                if ($round->getWinner()->equals($team)) {
                    $affectedRound = $competitionService->undoAdvanceTeam($team, $round);
                } else {
                    $response['origin']['winner'] = $teamId;
                    $affectedRound = $competitionService->advanceTeam($team, $round);
                }
                if ($affectedRound) {
                    $response['destination'] = [
                        'round_id' => $affectedRound->getId(),
                        'teams' => [],
                        'winner' => false,
                    ];
                    foreach ($affectedRound->getTeams() as $affectedTeam) {
                        $response['destination']['teams'][] = $affectedTeam->getId();
                    }
                }
            }
        }

        return new JsonResponse($response, JsonResponse::HTTP_OK);
    }

    /** @Route("/confirm_registration", name="api_confirm", methods={"PUT"}) */
    public function confirmRegistration(
        Request $request,
        PlayerRepository $playerRepository,
        RegistrationRepository $registrationRepository
    ): void {
        $competitionId = $request->get('competition_id');
        $twitchId = $request->get('twitch_id');
        $registration = $registrationRepository->findByCompetitionAndTwitchId($competitionId, $twitchId);
        if (!$registration->getIsConfirmed()) {
            $registration->setIsConfirmed(true);
            $registrationRepository->save($registration);
        }
    }
}
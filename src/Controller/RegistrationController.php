<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Competition;
use App\Entity\Registration;
use App\Repository\RegistrationRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/registration")
 */
class RegistrationController extends AbstractController
{
    /**
     * @Route("/new", name="registration_new", methods={"POST"})
     */
    public function new(
        Competition $competition,
        RegistrationRepository $registrationRepository
    ): Response {
        if (!($player = $this->getUser())) {
            $this->addFlash('error', 'Tienes que iniciar sesión para unirte a una competición');
        } elseif (!$competition->getIsOpen()) {
            $this->addFlash('error', 'Esta competición esta cerrada');
        } else {
            $registration = (new Registration())
                ->setCompetition($competition)
                ->setPlayer($player);
            $registrationRepository->save($registration);
        }

        return $this->redirectToRoute(
            $this->isGranted('ROLE_ADMIN') ? 'competition_edit' : 'competition_show', [
            'id' => $registration->getCompetition()->getId(),
        ]);
    }

    /**
     * @Route("/delete", name="registration_delete", methods={"POST"})
     */
    public function delete(
        Request $request,
        RegistrationRepository $registrationRepository,
        TeamRepository $teamRepository
    ): Response {
        if ($registrationId = $request->request->get('registration_id')) {
            $registration = $registrationRepository->find($registrationId);
        } elseif ($competitionId = $request->request->get('competition_id')) {
            $registration = $registrationRepository->findOneBy([
                'competition' => $competitionId,
                'player' => $this->getUser(),
            ]);
        }

        if (!$registration) {
            $this->addFlash('error', 'No se ha podido obtener la inscripción');
        } elseif (
            !$registration->getPlayer()->equals($this->getUser()) &&
            !$registration->getCompetition()->getStreamer()->equals($this->getUser()) &&
            !$this->isGranted('ROLE_ADMIN')
        ) {
            $this->addFlash('error', 'No puedes eliminar la inscripción de otro jugador');
        } elseif (!$registration->getCompetition()->getIsOpen()) {
            $this->addFlash('error', 'No puedes eliminar una inscripción de una competición cerrada');
        } else {
            if ($team = $teamRepository->findOneByPlayerAndCompetition($registration->getPlayer(), $registration->getCompetition())) {
                $team->removePlayer($registration->getPlayer());
                $teamRepository->save($team);
            }
            $registrationRepository->remove($registration);
        }

        return $this->redirectToRoute(
            $this->isGranted('ROLE_ADMIN') ? 'competition_edit' : 'competition_show', [
            'id' => $registration->getCompetition()->getId(),
        ]);
    }

    /** @Route("/toggle_confirmation", name="toggle_confirmation", methods={"POST"}) */
    public function toggleConfirmation(
        Request $request,
        Registration $registration,
        RegistrationRepository $registrationRepository
    ): Response {
        if (
            !$registration->getPlayer()->equals($this->getUser()) &&
            !$registration->getCompetition()->getStreamer()->equals($this->getUser()) &&
            !$this->isGranted('ROLE_ADMIN')
        ) {
            $this->addFlash('error', 'No puedes editar la inscripción de otro jugador');
        } elseif (!$registration->getCompetition()->getIsOpen()) {
            $this->addFlash('error', 'No puedes editar una inscripción si la competición esta cerrada');
        } else {
            $registration->setIsConfirmed('1' == $request->request->get('confirm'));
            $registrationRepository->save($registration);
        }

        return $this->redirectToRoute('competition_edit', ['id' => $registration->getCompetition()->getId()]);
    }
}

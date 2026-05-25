<?php

namespace App\Controller;

use App\Document\User;
use App\DTO\ReservationRequest;
use App\Exception\AppException;
use App\Repository\ReservationRepository;
use App\Repository\SessionRepository;
use App\Service\ReservationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/reservations', name: 'api_reservations_')]
class ReservationController extends AbstractApiController
{
    private const GROUPS = ['groups' => ['reservation:read', 'session:read']];

    /**
     * GET /api/reservations/me
     *
     * Returns the authenticated user's reservations.
     * Query param `active=true` (default) → active bookings only.
     * Query param `active=false` → all reservations including cancelled.
     */
    #[Route('/me', name: 'my_list', methods: ['GET'])]
    public function myReservations(Request $request, ReservationRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $activeOnly = $request->query->get('active', 'true') !== 'false';

        $reservations = $activeOnly
            ? $repo->findActiveByUserId((string) $user->getId())
            : $repo->findAllByUserId((string) $user->getId());

        return $this->json($reservations, Response::HTTP_OK, [], self::GROUPS);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        SessionRepository $sessionRepo,
        ReservationService $reservationService,
        ValidatorInterface $validator,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $dto = $this->parseBody($request, ReservationRequest::class);

        if ($error = $this->validateDto($dto, $validator)) {
            return $error;
        }

        $session = $sessionRepo->findOneBy(['id' => $dto->sessionId, 'active' => true]);
        if (!$session) {
            return $this->jsonError('Session not found.', Response::HTTP_NOT_FOUND);
        }

        try {
            $reservation = $reservationService->book($session, (string) $user->getId());
        } catch (AppException $e) {
            return $this->handleException($e);
        }

        return $this->json($reservation, Response::HTTP_CREATED, [], self::GROUPS);
    }

    #[Route('/{id}', name: 'cancel', methods: ['DELETE'])]
    public function cancel(
        string $id,
        ReservationRepository $reservationRepo,
        ReservationService $reservationService,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $reservation = $reservationRepo->findActiveByIdAndUser($id, (string) $user->getId());
        if (!$reservation) {
            return $this->jsonError('Reservation not found.', Response::HTTP_NOT_FOUND);
        }

        $reservationService->cancel($reservation);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

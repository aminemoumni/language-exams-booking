<?php

namespace App\Controller;

use App\DTO\SessionRequest;
use App\Repository\SessionRepository;
use App\Service\SessionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/sessions', name: 'api_sessions_')]
class SessionController extends AbstractApiController
{
    private const GROUPS = ['groups' => ['session:read']];

    /**
     * GET /api/sessions?page=1&limit=10&language=English&location=Paris
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request, SessionRepository $repo): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        $filters = array_filter([
            'language' => trim((string) $request->query->get('language', '')),
            'location' => trim((string) $request->query->get('location', '')),
        ]);

        ['data' => $sessions, 'total' => $total] = $repo->findPaginated($page, $limit, $filters);

        return $this->json([
            'data'       => $sessions,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ], Response::HTTP_OK, [], self::GROUPS);
    }

    /**
     * GET /api/sessions/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id, SessionRepository $repo): JsonResponse
    {
        $session = $repo->find($id);

        if (!$session || !$session->isActive()) {
            return $this->jsonError('Session not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->json($session, Response::HTTP_OK, [], self::GROUPS);
    }

    /**
     * POST /api/sessions  — Admin only
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        SessionService $sessionService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $dto = $this->parseBody($request, SessionRequest::class);

        if ($error = $this->validateDto($dto, $validator)) {
            return $error;
        }

        $session = $sessionService->create($dto);

        return $this->json($session, Response::HTTP_CREATED, [], self::GROUPS);
    }

    /**
     * PUT /api/sessions/{id}  — Admin only
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(
        string $id,
        Request $request,
        SessionRepository $repo,
        SessionService $sessionService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $session = $repo->find($id);

        if (!$session || !$session->isActive()) {
            return $this->jsonError('Session not found.', Response::HTTP_NOT_FOUND);
        }

        $dto = $this->parseBody($request, SessionRequest::class);

        if ($error = $this->validateDto($dto, $validator)) {
            return $error;
        }

        $session = $sessionService->update($session, $dto);

        return $this->json($session, Response::HTTP_OK, [], self::GROUPS);
    }

    /**
     * DELETE /api/sessions/{id}  — Admin only
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id, SessionRepository $repo, SessionService $sessionService): JsonResponse
    {
        $session = $repo->find($id);

        if (!$session || !$session->isActive()) {
            return $this->jsonError('Session not found.', Response::HTTP_NOT_FOUND);
        }

        $sessionService->delete($session);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

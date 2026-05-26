<?php

namespace App\Controller;

use App\Document\User;
use App\DTO\UpdateUserRequest;
use App\Exception\AppException;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_user_')]
class UserController extends AbstractApiController
{
    private const GROUPS = ['groups' => ['user:read']];

    #[Route('/me', name: 'me_get', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json($this->getUser(), Response::HTTP_OK, [], self::GROUPS);
    }

    #[Route('/me', name: 'me_update', methods: ['PUT'])]
    public function updateMe(
        Request $request,
        UserService $userService,
        ValidatorInterface $validator,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $dto  = $this->parseBody($request, UpdateUserRequest::class);

        if ($error = $this->validateDto($dto, $validator)) {
            return $error;
        }

        try {
            $user = $userService->updateProfile($user, $dto);
        } catch (AppException $e) {
            return $this->handleException($e);
        }

        return $this->json($user, Response::HTTP_OK, [], self::GROUPS);
    }
}

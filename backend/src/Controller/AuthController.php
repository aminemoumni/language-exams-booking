<?php

namespace App\Controller;

use App\DTO\RegisterRequest;
use App\Exception\AppException;
use App\Service\UserService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractApiController
{
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Intercepted by the json_login firewall — this code is unreachable.');
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserService $userService,
        ValidatorInterface $validator,
    ): JsonResponse {
        $dto = $this->parseBody($request, RegisterRequest::class);

        if ($error = $this->validateDto($dto, $validator)) {
            return $error;
        }

        try {
            $user = $userService->register($dto);
        } catch (AppException $e) {
            return $this->handleException($e);
        }

        return $this->json($user, Response::HTTP_CREATED, [], ['groups' => ['user:read']]);
    }
}

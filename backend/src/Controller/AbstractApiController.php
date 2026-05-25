<?php

namespace App\Controller;

use App\Exception\AppException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractApiController extends AbstractController
{
    /**
     * Decodes JSON body and maps it to a DTO via constructor reflection.
     *
     * Missing non-nullable fields receive their type's zero-value so the DTO
     * can always be instantiated — the validator then reports the actual errors
     * with a proper 422 instead of a 500 TypeError.
     *
     * @template T of object
     * @param class-string<T> $dtoClass
     * @return T
     */
    protected function parseBody(Request $request, string $dtoClass): object
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $reflection = new \ReflectionClass($dtoClass);
        $args = [];

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
            $value = $data[$param->getName()] ?? null;

            if ($value === null) {
                if ($param->isDefaultValueAvailable()) {
                    $value = $param->getDefaultValue();
                } elseif (!($param->getType()?->allowsNull() ?? true)) {
                    // Non-nullable, no default — use zero-value so instantiation
                    // succeeds and the validator catches the missing field.
                    $value = match ($param->getType()?->getName()) {
                        'string'         => '',
                        'int', 'float'   => 0,
                        'bool'           => false,
                        'array'          => [],
                        default          => null,
                    };
                }
            }

            $args[] = $value;
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Validates a DTO and returns a 422 JsonResponse on error, or null if valid.
     */
    protected function validateDto(object $dto, ValidatorInterface $validator): ?JsonResponse
    {
        $violations = $validator->validate($dto);

        if (count($violations) === 0) {
            return null;
        }

        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $this->json(
            ['message' => 'Validation failed.', 'errors' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    /**
     * Translates an AppException into a JSON error response.
     * The exception owns its status code — the controller just delegates.
     */
    protected function handleException(AppException $e): JsonResponse
    {
        return $this->jsonError($e->getMessage(), $e->getStatusCode());
    }

    /**
     * Returns a standardised JSON error response.
     */
    protected function jsonError(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->json(['message' => $message], $status);
    }
}

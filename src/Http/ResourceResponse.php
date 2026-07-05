<?php
declare(strict_types=1);

namespace Nemesis\Http;

use Nemesis\Core\Paginator;

class ResourceResponse
{
    protected static function normalize(mixed $resource): mixed
    {
        if ($resource instanceof JsonResource) {
            return $resource->toArray();
        }

        if ($resource instanceof Paginator) {
            return $resource->toArray();
        }

        if (is_object($resource) && method_exists($resource, 'toArray')) {
            return $resource->toArray();
        }

        return $resource;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): Response
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => self::normalize($data),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return Response::json($payload, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created'): Response
    {
        return self::success($data, $message, 201);
    }

    public static function updated(mixed $data = null, string $message = 'Updated'): Response
    {
        return self::success($data, $message, 200);
    }

    public static function deleted(string $message = 'Deleted'): Response
    {
        return self::success(null, $message, 200);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = null): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = self::normalize($errors);
        }

        return Response::json($payload, $status);
    }

    public static function validationError(mixed $errors, string $message = 'Validation failed'): Response
    {
        return self::error($message, 422, $errors);
    }

    public static function paginated(Paginator $paginator, string $message = 'Success'): Response
    {
        $data = $paginator->toArray();

        return Response::json([
            'success' => true,
            'message' => $message,
            'data' => $data['data'],
            'meta' => $data['meta'],
        ], 200);
    }
}

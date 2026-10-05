<?php
declare(strict_types=1);

namespace Nemesis\Http;

use Nemesis\Core\ValidationException;
use Nemesis\Core\Validator;
use Nemesis\Exceptions\AuthorizationException;

/**
 * Base request object for controller actions that own their validation rules.
 *
 * FormRequest instances are hydrated from the request that has already passed
 * through middleware. The router resolves and validates them only when the
 * first controller parameter is typed as this class or one of its subclasses.
 */
class FormRequest extends Request
{
    /** @var array<string, mixed>|null */
    protected ?array $validatedData = null;

    /**
     * Determine whether the current caller may make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Return custom messages keyed by `field.rule`.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Hydrate this typed request from the request that ran through middleware.
     */
    public function initializeFrom(Request $request): static
    {
        $request->copyStateTo($this);
        $this->validatedData = null;

        return $this;
    }

    /**
     * Authorize and validate the request once.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function validateResolved(): void
    {
        if ($this->validatedData !== null) {
            return;
        }

        if (!$this->authorize()) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $rules = $this->rules();
        $validator = new Validator();
        $validator->setMessages($this->messages());

        if (!$validator->validate($this->all(), $rules)) {
            throw new ValidationException($validator->errors());
        }

        $this->validatedData = $validator->validated($this->all(), $rules);
    }

    /**
     * Return the validated payload or one validated field.
     */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        $this->validateResolved();

        if ($key === null) {
            return $this->validatedData ?? [];
        }

        return array_key_exists($key, $this->validatedData ?? [])
            ? $this->validatedData[$key]
            : $default;
    }
}

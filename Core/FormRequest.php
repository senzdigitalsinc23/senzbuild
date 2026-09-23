<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Interfaces\RequestInterface;

abstract class FormRequest
{
    protected RequestInterface $request;
    protected array $errors = [];
    protected array $validated = [];
    protected bool $authorized = true;

    public function __construct(RequestInterface $request)
    {
        $this->request = $request;
    }

    abstract public function rules(): array;

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [];
    }

    public function validate(): array
    {
        if (!$this->authorize()) {
            throw new \RuntimeException('Unauthorized action', 403);
        }

        $data = $this->request->getBodyParams();
        $rules = $this->rules();
        $messages = $this->messages();
        $validator = new Validator($data, $rules, null, $messages);

        if ($validator->fails()) {
            $this->errors = $validator->errors();
            throw new \App\Exceptions\ValidationException(
                $this->errors,
                'Validation failed'
            );
        }

        $this->validated = $data;
        return $this->validated;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }
}
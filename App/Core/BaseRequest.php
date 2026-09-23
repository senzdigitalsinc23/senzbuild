<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base Request class for Form-style validation
 *
 * Extends the core Validator to provide a standardized way to define
 * validation rules for incoming API requests.
 */
abstract class BaseRequest extends Validator
{
    /**
     * Define the validation rules for this request
     *
     * @return array Rules mapping field => rule_string
     */
    abstract public function rules(): array;

    /**
     * Constructor overrides the Validator constructor to automatically
     * trigger validation on the provided data.
     */
    public function __construct(array $data, $db = null, array $customMessages = [])
    {
        // The Validator constructor calls validate() internally,
        // but we need the rules() method from this class.
        parent::__construct($data, $this->rules(), $db, $customMessages);
    }
}

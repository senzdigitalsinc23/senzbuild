<?php
declare(strict_types=1);

namespace App\Http\Requests;

use App\Core\BaseRequest;

class UserUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => 'string|min:3|max:50',
            'email'    => 'email|unique:users,email',
            'status'   => 'string|in:active,inactive,suspended',
            'role_id'  => 'integer|exists:roles,id',
        ];
    }
}

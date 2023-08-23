<?php

namespace Phyple\Larascaff\Http;

use Illuminate\Foundation\Http\FormRequest;

class Request extends FormRequest
{
    /**
     * Determine if the request passes the authorization check.
     *
     * @return bool
     */
    protected function authorize(): bool
    {
        return false;
    }
}
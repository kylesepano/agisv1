<?php

namespace App\Http\Requests\Iap;

use App\Models\IapBaicsMethod;
use Illuminate\Foundation\Http\FormRequest;

class IapBaicsMethodRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'methodType' => ['required', 'in:'.implode(',', IapBaicsMethod::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'performedBy' => ['required', 'integer', 'exists:users,id'],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'processReference' => ['nullable', 'string', 'max:255'],
            'performedOn' => ['required', 'date'],
            'procedure' => ['required', 'string', 'max:20000'],
            'result' => ['required', 'string', 'max:20000'],
            'limitations' => ['nullable', 'string', 'max:10000'],
            'reviewerId' => ['required', 'integer', 'exists:users,id'],
            'lockVersion' => [$this->route('method') ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}

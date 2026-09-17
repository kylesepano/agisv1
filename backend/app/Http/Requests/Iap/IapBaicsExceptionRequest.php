<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

class IapBaicsExceptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'componentId' => ['required', 'integer', 'exists:iap_baics_components,id'],
            'reason' => ['required', 'string', 'max:20000'],
            'authorityUserId' => ['required', 'integer', 'exists:users,id'],
            'compensatingEvidence' => ['required', 'string', 'max:20000'],
            'expiryDate' => ['required', 'date', 'after:today'],
            'lockVersion' => [$this->route('exception') ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}

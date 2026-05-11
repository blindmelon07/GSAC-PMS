<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'branch_id'    => ['required', 'integer', 'exists:branches,id'],
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'due_days'     => ['nullable', 'integer', 'min:1', 'max:90'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ];
    }
}

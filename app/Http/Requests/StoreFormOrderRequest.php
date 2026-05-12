<?php

namespace App\Http\Requests;

use App\Models\FormOrder;
use App\Models\FormType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FormOrder::class);
    }

    public function rules(): array
    {
        return [
            'priority'             => ['required', Rule::in(['low', 'normal', 'urgent'])],
            'notes'                => ['nullable', 'string', 'max:1000'],
            'needed_by'            => ['nullable', 'date', 'after:today'],
            'items'                => ['required', 'array', 'min:1', 'max:10'],
            'items.*.form_type_id'   => [
                'required', 'integer',
                Rule::exists('form_types', 'id')->where('is_active', true),
            ],
            'items.*.printer_type' => ['required', Rule::in(['consumable', 'non_consumable'])],
            'items.*.quantity'     => ['required', 'integer', 'min:1', 'max:50000'],
            'items.*.notes'        => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            foreach ($this->items ?? [] as $index => $item) {
                if (empty($item['form_type_id']) || empty($item['quantity'])) continue;

                $formType = FormType::find($item['form_type_id']);

                if ($formType && $item['quantity'] < $formType->minimum_order) {
                    $v->errors()->add("items.{$index}.quantity",
                        "Minimum order for {$formType->name} is {$formType->minimum_order}.");
                }

                if ($formType && $formType->maximum_order && $item['quantity'] > $formType->maximum_order) {
                    $v->errors()->add("items.{$index}.quantity",
                        "Maximum order for {$formType->name} is {$formType->maximum_order}.");
                }
            }

            $ids = array_column($this->items ?? [], 'form_type_id');
            if (count($ids) !== count(array_unique($ids))) {
                $v->errors()->add('items', 'Duplicate form types are not allowed. Combine quantities instead.');
            }
        });
    }
}

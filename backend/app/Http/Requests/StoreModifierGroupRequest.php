<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-menu') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $restaurantId = $this->user()->restaurant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('modifier_groups')->where('restaurant_id', $restaurantId),
            ],
            'min_selections' => ['sometimes', 'integer', 'min:0', 'max:20'],
            'max_selections' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'required' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'options' => ['sometimes', 'array'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.name' => ['required_with:options', 'string', 'max:80', 'distinct'],
            'options.*.price' => ['required_with:options', 'numeric', 'min:0', 'max:9999999.99'],
            'options.*.available' => ['sometimes', 'boolean'],
            'options.*.active' => ['sometimes', 'boolean'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $min = (int) $this->input('min_selections', 0);
            $max = (int) $this->input('max_selections', 1);
            $required = $this->boolean('required');
            $activeChoices = collect($this->input('options', []))
                ->filter(fn (array $option): bool => ($option['active'] ?? true) && ($option['available'] ?? true))
                ->count();

            if ($min > $max) {
                $validator->errors()->add('min_selections', 'Minimum selections cannot exceed maximum selections.');
            }
            if ($required && $min < 1) {
                $validator->errors()->add('min_selections', 'A required group must require at least one choice.');
            }
            if ($min > $activeChoices) {
                $validator->errors()->add('options', 'Provide enough active, available choices to satisfy the minimum selection rule.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => $this->string('name')->trim()->toString()]);
    }
}

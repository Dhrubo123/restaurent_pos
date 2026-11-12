<?php

namespace App\Http\Requests;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateModifierGroupRequest extends FormRequest
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
        /** @var ModifierGroup $group */
        $group = $this->route('modifierGroup');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('modifier_groups')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->ignore($group->id),
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
            /** @var ModifierGroup $group */
            $group = $this->route('modifierGroup');
            $min = (int) $this->input('min_selections', $group->min_selections);
            $max = (int) $this->input('max_selections', $group->max_selections);
            $required = $this->has('required') ? $this->boolean('required') : $group->required;

            if ($min > $max) {
                $validator->errors()->add('min_selections', 'Minimum selections cannot exceed maximum selections.');
            }
            if ($required && $min < 1) {
                $validator->errors()->add('min_selections', 'A required group must require at least one choice.');
            }

            $activeChoices = $this->has('options')
                ? collect($this->input('options', []))
                    ->filter(fn (array $option): bool => ($option['active'] ?? true) && ($option['available'] ?? true))
                    ->count()
                : ModifierOption::query()
                    ->where('modifier_group_id', $group->id)
                    ->where('active', true)
                    ->where('available', true)
                    ->count();
            if ($min > $activeChoices) {
                $validator->errors()->add('options', 'Provide enough active, available choices to satisfy the minimum selection rule.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => $this->string('name')->trim()->toString()]);
        }
    }
}

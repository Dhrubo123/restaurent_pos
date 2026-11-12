<?php

namespace App\Http\Requests;

use App\Models\MenuCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreMenuCategoryRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('menu_categories')->where('restaurant_id', $this->user()->restaurant_id),
            ],
            'default_station' => ['sometimes', 'required', Rule::in(MenuCategory::STATIONS)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = ['name' => $this->string('name')->trim()->toString()];

        if ($this->has('default_station')) {
            $normalized['default_station'] = Str::upper($this->string('default_station')->trim()->toString());
        }

        $this->merge($normalized);
    }
}

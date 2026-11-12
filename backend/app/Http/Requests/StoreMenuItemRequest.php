<?php

namespace App\Http\Requests;

use App\Models\MenuItem;
use App\Models\MenuSubcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMenuItemRequest extends FormRequest
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
            'menu_category_id' => [
                'required',
                Rule::exists('menu_categories', 'id')
                    ->where('restaurant_id', $restaurantId)
                    ->where('active', true),
            ],
            'menu_subcategory_id' => ['nullable', 'integer'],
            'tax_category_id' => [
                'nullable',
                Rule::exists('tax_categories', 'id')
                    ->where('restaurant_id', $restaurantId)
                    ->where('active', true),
            ],
            'sku' => [
                'required',
                'string',
                'max:40',
                Rule::unique('menu_items')->where('restaurant_id', $restaurantId),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('menu_items')
                    ->where('restaurant_id', $restaurantId)
                    ->where('menu_category_id', $this->input('menu_category_id')),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'image_path' => ['nullable', 'string', 'max:750000'],
            'station' => ['required', Rule::in(MenuItem::STATIONS)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'available' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'modifier_group_ids' => ['sometimes', 'array'],
            'modifier_group_ids.*' => [
                'integer',
                Rule::exists('modifier_groups', 'id')->where('restaurant_id', $restaurantId),
            ],
            'variants' => ['sometimes', 'array'],
            'variants.*.sku' => [
                'required_with:variants',
                'string',
                'max:40',
                'distinct',
                Rule::unique('item_variants', 'sku')->where('restaurant_id', $restaurantId),
            ],
            'variants.*.name' => ['required_with:variants', 'string', 'max:80', 'distinct'],
            'variants.*.price' => ['required_with:variants', 'numeric', 'min:0', 'max:9999999.99'],
            'variants.*.available' => ['sometimes', 'boolean'],
            'variants.*.active' => ['sometimes', 'boolean'],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $subcategoryId = $this->input('menu_subcategory_id');
            if ($subcategoryId === null || $subcategoryId === '') {
                return;
            }

            $valid = MenuSubcategory::query()
                ->whereKey($subcategoryId)
                ->where('restaurant_id', $this->user()->restaurant_id)
                ->where('menu_category_id', $this->input('menu_category_id'))
                ->where('active', true)
                ->exists();
            if (! $valid) {
                $validator->errors()->add('menu_subcategory_id', 'Choose an active subcategory from the selected category.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $normalized = [
            'sku' => Str::upper($this->string('sku')->trim()->toString()),
            'name' => $this->string('name')->trim()->toString(),
        ];

        if ($this->has('station')) {
            $normalized['station'] = Str::upper($this->string('station')->trim()->toString());
        }

        if ($this->has('description') && $this->input('description') !== null) {
            $normalized['description'] = $this->string('description')->trim()->toString();
        }

        $this->merge($normalized);
    }
}

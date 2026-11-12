<?php

namespace App\Http\Requests;

use App\Models\MenuItem;
use App\Models\MenuSubcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMenuItemRequest extends FormRequest
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
        /** @var MenuItem $menuItem */
        $menuItem = $this->route('menuItem');
        $restaurantId = $this->user()->restaurant_id;
        $categoryId = $this->input('menu_category_id', $menuItem->menu_category_id);

        $priceChanging = $this->has('price')
            && is_numeric($this->input('price'))
            && bccomp(number_format((float) $this->input('price'), 2, '.', ''), (string) $menuItem->price, 2) !== 0;

        return [
            'menu_category_id' => [
                'sometimes',
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
                'sometimes',
                'required',
                'string',
                'max:40',
                Rule::unique('menu_items')
                    ->where('restaurant_id', $restaurantId)
                    ->ignore($menuItem->id),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('menu_items')
                    ->where('restaurant_id', $restaurantId)
                    ->where('menu_category_id', $categoryId)
                    ->ignore($menuItem->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'image_path' => ['nullable', 'string', 'max:750000'],
            'station' => ['sometimes', 'required', Rule::in(MenuItem::STATIONS)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999.99'],
            'reason' => [$priceChanging ? 'required' : 'nullable', 'string', 'min:3', 'max:500'],
            'available' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'modifier_group_ids' => ['sometimes', 'array'],
            'modifier_group_ids.*' => [
                'integer',
                Rule::exists('modifier_groups', 'id')->where('restaurant_id', $restaurantId),
            ],
            'variants' => ['sometimes', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.sku' => ['required_with:variants', 'string', 'max:40', 'distinct'],
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

            /** @var MenuItem $menuItem */
            $menuItem = $this->route('menuItem');
            $categoryId = $this->input('menu_category_id', $menuItem->menu_category_id);
            $valid = MenuSubcategory::query()
                ->whereKey($subcategoryId)
                ->where('restaurant_id', $this->user()->restaurant_id)
                ->where('menu_category_id', $categoryId)
                ->where('active', true)
                ->exists();
            if (! $valid) {
                $validator->errors()->add('menu_subcategory_id', 'Choose an active subcategory from the selected category.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('sku')) {
            $normalized['sku'] = Str::upper($this->string('sku')->trim()->toString());
        }

        if ($this->has('name')) {
            $normalized['name'] = $this->string('name')->trim()->toString();
        }

        if ($this->has('station')) {
            $normalized['station'] = Str::upper($this->string('station')->trim()->toString());
        }

        if ($this->has('reason') && $this->input('reason') !== null) {
            $normalized['reason'] = $this->string('reason')->trim()->toString();
        }

        $this->merge($normalized);
    }
}

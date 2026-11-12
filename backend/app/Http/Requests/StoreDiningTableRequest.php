<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-floor') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => [
                'required',
                Rule::exists('zones', 'id')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->where('active', true),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('dining_tables')->where('restaurant_id', $this->user()->restaurant_id),
            ],
            'display_name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('dining_tables')->where('restaurant_id', $this->user()->restaurant_id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper($this->string('code')->trim()->toString()),
            'display_name' => $this->string('display_name')->trim()->toString(),
        ]);
    }
}

<?php

namespace App\Http\Requests;

use App\Models\DiningTable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDiningTableRequest extends FormRequest
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
        /** @var DiningTable $diningTable */
        $diningTable = $this->route('diningTable');

        return [
            'zone_id' => [
                'sometimes',
                Rule::exists('zones', 'id')
                    ->where('restaurant_id', $this->user()->restaurant_id)
                    ->where('active', true),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('dining_tables')->where('restaurant_id', $this->user()->restaurant_id)->ignore($diningTable->id),
            ],
            'display_name' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('dining_tables')->where('restaurant_id', $this->user()->restaurant_id)->ignore($diningTable->id),
            ],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('code')) {
            $normalized['code'] = Str::upper($this->string('code')->trim()->toString());
        }

        if ($this->has('display_name')) {
            $normalized['display_name'] = $this->string('display_name')->trim()->toString();
        }

        $this->merge($normalized);
    }
}

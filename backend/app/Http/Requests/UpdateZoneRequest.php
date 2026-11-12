<?php

namespace App\Http\Requests;

use App\Models\Zone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZoneRequest extends FormRequest
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
        /** @var Zone $zone */
        $zone = $this->route('zone');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('zones')->where('restaurant_id', $this->user()->restaurant_id)->ignore($zone->id),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => $this->string('name')->trim()->toString()]);
        }
    }
}

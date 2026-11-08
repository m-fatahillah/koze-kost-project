<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        if (!Auth::check()) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();

        return $user->hasRole('Owner')
            && $property instanceof Property
            && $property->owner_id === Auth::id();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'property_type_id' => 'required|integer|exists:property_types,id',
            'gender_restriction' => 'required|in:putra,putri,campur',
            'price' => 'required|numeric|min:0',
            'price_type' => 'required|in:daily,monthly,yearly',
            'address' => 'required|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'total_rooms' => 'required|integer|min:1',
            'available_rooms' => 'required|integer|min:0|lte:total_rooms',
            'status' => 'required|in:active,inactive',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
            'facility_ids' => 'nullable|array',
            'facility_ids.*' => 'integer|exists:facilities,id',
            'new_thumbnail' => 'nullable|image|max:5120',
            'new_detail_photos' => 'nullable|array',
            'new_detail_photos.*' => 'image|max:5120',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'available_rooms.lte' => 'Kamar tersedia tidak boleh melebihi total kamar.',
        ];
    }
}

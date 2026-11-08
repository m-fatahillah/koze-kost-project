<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SavedPropertyController extends Controller
{
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
        ]);

        $user = $request->user();
        $propertyId = (int) $validated['property_id'];

        $alreadySaved = $user->savedProperties()->where('property_id', $propertyId)->exists();

        if ($alreadySaved) {
            $user->savedProperties()->detach($propertyId);
        } else {
            $user->savedProperties()->syncWithoutDetaching([$propertyId]);
        }

        $savedIds = $user->savedProperties()->pluck('properties.id')->values()->all();

        return response()->json([
            'saved' => !$alreadySaved,
            'savedIds' => $savedIds,
            'savedCount' => count($savedIds),
        ]);
    }
}

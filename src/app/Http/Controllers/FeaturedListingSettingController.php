<?php

namespace App\Http\Controllers;

use App\Models\FeaturedListingSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FeaturedListingSettingController extends Controller
{
    public function index()
    {
        return Inertia::render('FeaturedListing/Settings', [
            'setting' => FeaturedListingSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'price_per_month' => 'required|numeric|min:0',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
        ]);

        $setting = FeaturedListingSetting::query()->orderBy('id')->first();

        if ($setting) {
            $setting->update($validated);
        } else {
            FeaturedListingSetting::create($validated);
        }

        return redirect()->back()->with('success', 'Pengaturan listing berhasil diperbarui.');
    }
}

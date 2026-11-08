<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PropertyTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = \App\Models\PropertyType::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Paginate - 25 items per page
        $propertyTypes = $query->orderBy('created_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        return \Inertia\Inertia::render('PropertyTypes/Index', [
            'property_types' => $propertyTypes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        \App\Models\PropertyType::create($validated);

        return redirect()->back()->with('success', 'Tipe properti berhasil ditambahkan.');
    }

    public function update(Request $request, \App\Models\PropertyType $propertyType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $propertyType->update($validated);

        return redirect()->back()->with('success', 'Tipe properti berhasil diperbarui.');
    }

    public function destroy(\App\Models\PropertyType $propertyType)
    {
        $propertyType->delete();

        return redirect()->back()->with('success', 'Tipe properti berhasil dihapus.');
    }
}

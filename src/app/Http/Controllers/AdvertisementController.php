<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Traits\UploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdvertisementController extends Controller
{
    use UploadTrait;

    private const PLACEMENTS = ['header', 'sidebar', 'interstitial', 'detail'];
    private const STATUSES = ['active', 'inactive', 'completed'];

    public function index(Request $request)
    {
        $query = Advertisement::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('advertiser_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('placement')) {
            $query->where('placement', $request->input('placement'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $advertisements = $query->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Advertisements/Index', [
            'advertisements' => $advertisements,
            'filters' => $request->only(['search', 'placement', 'status']),
            'placements' => self::PLACEMENTS,
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'advertiser_name' => 'required|string|max:255',
            'image' => 'required|file|image|max:5120',
            'target_url' => 'required|url|max:2048',
            'placement' => 'required|in:' . implode(',', self::PLACEMENTS),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        DB::beginTransaction();
        try {
            $imagePath = $this->upload('advertisements', $request->file('image'));

            Advertisement::create([
                'title' => $validated['title'],
                'advertiser_name' => $validated['advertiser_name'],
                'image_url' => $imagePath,
                'target_url' => $validated['target_url'],
                'placement' => $validated['placement'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => $validated['status'],
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Iklan berhasil ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal menyimpan iklan: ' . $e->getMessage()]);
        }
    }

    public function update(Request $request, Advertisement $advertisement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'advertiser_name' => 'required|string|max:255',
            'image' => 'nullable|file|image|max:5120',
            'target_url' => 'required|url|max:2048',
            'placement' => 'required|in:' . implode(',', self::PLACEMENTS),
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:' . implode(',', self::STATUSES),
        ]);

        DB::beginTransaction();
        try {
            $data = [
                'title' => $validated['title'],
                'advertiser_name' => $validated['advertiser_name'],
                'target_url' => $validated['target_url'],
                'placement' => $validated['placement'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => $validated['status'],
            ];

            if ($request->hasFile('image')) {
                if ($advertisement->image_url) {
                    $this->remove($advertisement->image_url);
                }
                $data['image_url'] = $this->upload('advertisements', $request->file('image'));
            }

            $advertisement->update($data);

            DB::commit();
            return redirect()->back()->with('success', 'Iklan berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal memperbarui iklan: ' . $e->getMessage()]);
        }
    }

    public function destroy(Advertisement $advertisement)
    {
        if ($advertisement->image_url) {
            $this->remove($advertisement->image_url);
        }

        $advertisement->delete();

        return redirect()->back()->with('success', 'Iklan berhasil dihapus.');
    }
}

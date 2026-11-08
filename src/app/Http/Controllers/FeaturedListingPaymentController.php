<?php

namespace App\Http\Controllers;

use App\Models\FeaturedListingPayment;
use App\Models\FeaturedListingSetting;
use App\Models\Property;
use App\Models\User;
use App\Traits\UploadTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeaturedListingPaymentController extends Controller
{
    use UploadTrait;

    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $query = FeaturedListingPayment::with([
            'property:id,name,owner_id',
            'owner:id,name,email',
            'verifier:id,name',
        ]);

        if (!$user->hasRole('Admin')) {
            $query->where('owner_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        return Inertia::render('FeaturedListing/Index', [
            'payments' => $payments,
            'filters' => $request->only(['status']),
            'isAdmin' => $user->hasRole('Admin'),
            'setting' => FeaturedListingSetting::current(),
        ]);
    }

    public function create(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $property = Property::where('owner_id', $user->id)
            ->findOrFail($request->input('property_id'));

        return response()->json([
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'is_featured' => (bool) $property->is_featured,
                'featured_until' => $property->featured_until,
            ],
            'setting' => FeaturedListingSetting::current(),
        ]);
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'months' => 'required|integer|min:1|max:24',
            'payment_proof' => 'required|file|image|max:5120',
        ]);

        $property = Property::where('owner_id', $user->id)->findOrFail($validated['property_id']);

        $setting = FeaturedListingSetting::current();
        if (!$setting->exists || (float) $setting->price_per_month <= 0) {
            return redirect()->back()->withErrors([
                'error' => 'Pengaturan listing belum dikonfigurasi oleh admin.',
            ]);
        }

        $hasPendingForProperty = FeaturedListingPayment::where('property_id', $property->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingForProperty) {
            return redirect()->back()->withErrors([
                'error' => 'Pengajuan listing untuk properti ini sedang menunggu verifikasi.',
            ]);
        }

        DB::beginTransaction();
        try {
            $proofPath = $this->upload('payment-proofs', $request->file('payment_proof'));

            FeaturedListingPayment::create([
                'property_id' => $property->id,
                'owner_id' => $user->id,
                'months' => (int) $validated['months'],
                'price_per_month' => $setting->price_per_month,
                'total_amount' => (float) $setting->price_per_month * (int) $validated['months'],
                'bank_name' => $setting->bank_name,
                'account_number' => $setting->account_number,
                'account_holder_name' => $setting->account_holder_name,
                'payment_proof' => $proofPath,
                'status' => 'pending',
            ]);

            DB::commit();
            return redirect()->route('featured-listing.index')
                ->with('success', 'Pengajuan listing dikirim. Menunggu verifikasi admin.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'error' => 'Gagal mengirim pengajuan: ' . $e->getMessage(),
            ]);
        }
    }

    public function verify(Request $request, FeaturedListingPayment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->back()->withErrors([
                'error' => 'Pengajuan sudah diproses sebelumnya.',
            ]);
        }

        $property = $payment->property;
        if (!$property) {
            return redirect()->back()->withErrors(['error' => 'Properti tidak ditemukan.']);
        }

        DB::beginTransaction();
        try {
            $now = Carbon::now();

            // Extend dari expiry existing kalau masih aktif
            $existingExpiry = $property->featured_until;
            $base = ($existingExpiry && $existingExpiry->isFuture()) ? $existingExpiry : $now;
            $newExpiry = $base->copy()->addMonths($payment->months);

            $payment->update([
                'status' => 'verified',
                'verified_at' => $now,
                'verified_by' => Auth::id(),
                'starts_at' => $base,
                'expires_at' => $newExpiry,
            ]);

            $property->update([
                'is_featured' => true,
                'featured_until' => $newExpiry,
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Pembayaran diverifikasi. Listing aktif sampai ' . $newExpiry->format('d M Y H:i') . '.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal memverifikasi: ' . $e->getMessage()]);
        }
    }

    public function reject(Request $request, FeaturedListingPayment $payment)
    {
        if ($payment->status !== 'pending') {
            return redirect()->back()->withErrors([
                'error' => 'Pengajuan sudah diproses sebelumnya.',
            ]);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $payment->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'verified_at' => Carbon::now(),
            'verified_by' => Auth::id(),
        ]);

        return redirect()->back()->with('success', 'Pengajuan ditolak.');
    }
}

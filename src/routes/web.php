<?php

use App\Http\Controllers\AdvertisementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\FeaturedListingPaymentController;
use App\Http\Controllers\FeaturedListingSettingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SavedPropertyController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/properties/markers', [PropertyController::class, 'markers'])->name('properties.markers');
Route::get('/facilities/list', [FacilityController::class, 'list'])->name('facilities.list');
Route::get('/discover', [PageController::class, 'discover'])->name('discover');
Route::get('/property/{id}', [PageController::class, 'propertyShow'])->whereNumber('id')->name('property.show');
Route::get('/discover/map', [PropertyController::class, 'discoveryMap'])->name('discover.map');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);

    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);

    Route::get('forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleController::class, 'callback'])->name('google.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Email Verification Routes
    Route::get('email/verify', [VerifyEmailController::class, 'show'])->name('verification.notice');
    Route::get('email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
    Route::post('email/verification-notification', [VerifyEmailController::class, 'resend'])
                ->middleware('throttle:6,1')
                ->name('verification.send');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat/history', [ChatController::class, 'history'])->name('chat.history');
    Route::post('/chat/message', [ChatController::class, 'message'])->name('chat.message');
    Route::post('/chat/clear', [ChatController::class, 'clear'])->name('chat.clear');

    Route::get('/messages/{conversation?}', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead'])->name('conversations.read');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('/heartbeat', function () {
        auth()->user()->update(['last_seen_at' => now()]);
        return response()->noContent();
    })->name('heartbeat');

    Route::get('/saved', [PageController::class, 'saved'])->name('saved');
    Route::post('/saved/toggle', [SavedPropertyController::class, 'toggle'])->name('saved.toggle');

    Route::post('/properties/{property}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // Profile accessible by all roles including Seeker
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('profile', [ProfileController::class, 'updateProfile'])->name('profile.updateProfile');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.updatePassword');
});

Route::middleware(['auth', 'verified', 'redirect.if.seeker'])->group(function () {
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');

    // CRUD Routes
    Route::resource('facilities', FacilityController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('property-types', \App\Http\Controllers\PropertyTypeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('faqs', FaqController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('users', UserController::class)
        ->only(['index', 'store', 'update'])
        ->middleware('role:Admin');

    Route::resource('facilities', FacilityController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('role:Admin');
    
    Route::get('properties', [PropertyController::class, 'index'])
        ->middleware('role:Admin|Owner')
        ->name('properties.index');

    Route::middleware('role:Owner')->group(function () {
        Route::patch('properties/{property}/status', [PropertyController::class, 'updateStatus'])
            ->name('properties.updateStatus');
            
        Route::delete('properties/{property}/photos/{image}', [PropertyController::class, 'destroyImage'])
            ->name('properties.destroyImage');
            
        Route::resource('properties', PropertyController::class)->except(['index', 'show']);
    });

    Route::resource('faqs', FaqController::class)
        ->only(['index', 'store', 'update', 'destroy'])
        ->middleware('role:Admin');

    // Featured listing — settings (Admin only)
    Route::middleware('role:Admin')->group(function () {
        Route::get('featured-listing/settings', [FeaturedListingSettingController::class, 'index'])
            ->name('featured-listing.settings.index');
        Route::put('featured-listing/settings', [FeaturedListingSettingController::class, 'update'])
            ->name('featured-listing.settings.update');

        Route::post('featured-listing/payments/{payment}/verify', [FeaturedListingPaymentController::class, 'verify'])
            ->name('featured-listing.payments.verify');
        Route::post('featured-listing/payments/{payment}/reject', [FeaturedListingPaymentController::class, 'reject'])
            ->name('featured-listing.payments.reject');

        Route::get('advertisements', [AdvertisementController::class, 'index'])->name('advertisements.index');
        Route::post('advertisements', [AdvertisementController::class, 'store'])->name('advertisements.store');
        Route::post('advertisements/{advertisement}', [AdvertisementController::class, 'update'])->name('advertisements.update');
        Route::delete('advertisements/{advertisement}', [AdvertisementController::class, 'destroy'])->name('advertisements.destroy');
    });

    // Featured listing — payments (Admin sees all, Owner sees own)
    Route::middleware('role:Admin|Owner')->group(function () {
        Route::get('featured-listing', [FeaturedListingPaymentController::class, 'index'])
            ->name('featured-listing.index');
    });

    // Owner submits new payment
    Route::middleware('role:Owner')->group(function () {
        Route::get('featured-listing/create', [FeaturedListingPaymentController::class, 'create'])
            ->name('featured-listing.create');
        Route::post('featured-listing', [FeaturedListingPaymentController::class, 'store'])
            ->name('featured-listing.store');
    });
});

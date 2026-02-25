<?php

use App\Http\Controllers\Admin\StoreSettingsController as AdminStoreSettings;
use App\Http\Controllers\Api\Store\AccountPagesController;
use App\Http\Controllers\Api\Store\CheckoutController;
use App\Http\Controllers\Api\Store\MessageController;
use App\Http\Controllers\Api\Store\MyOrdersApiController;
use App\Http\Controllers\Api\Store\NewsletterController;
use App\Http\Controllers\QuickBooksController;
use App\Http\Controllers\StoreAuthController;
use App\Http\Controllers\StoreFrontController;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

// ------------------------------------------------------------------\\
// Passport::routes();

// Login route will be defined explicitly below with middleware

Route::get('password/find/{token}', 'PasswordResetController@find');

// Route::middleware(['web','auth:web','Is_Active'])->group(function () {
//     Route::get('/admin/store/settings', [AdminStoreSettings::class, 'show']);
//     Route::post('/admin/store/settings', [AdminStoreSettings::class, 'update']);
// });

$installed = Storage::disk('public')->exists('installed');

// ------------------------------------------------------------------\\
// ONLINE STORE ROUTES (Only if installed)
if ($installed === true) {

    Route::middleware(['web', 'request.safety', 'store.enabled'])->group(function () {

        Route::prefix('online_store')->group(function () {

            Route::get('/lang/{locale}', function ($locale) {
                $supported = ['en', 'fr', 'es', 'ar'];

                // Use provided locale if supported, otherwise fallback to 'en'
                $chosen = in_array($locale, $supported, true) ? $locale : 'en';

                // Store in session
                session(['locale' => $chosen]);

                // Optionally persist for a year via cookie
                Cookie::queue('locale', $chosen, 60 * 24 * 365, '/');

                return back();
            })->name('lang.switch');

            Route::get('/', [StoreFrontController::class, 'index'])->name('store.index');
            Route::get('/shop', [StoreFrontController::class, 'shop'])->name('store.shop');
            Route::get('/contact', [StoreFrontController::class, 'contact'])->name('store.contact');
            Route::post('/contact', [StoreFrontController::class, 'sendContact'])->name('store.contact.send');
            Route::post('/store/orders', [CheckoutController::class, 'store'])->name('store.orders.store');
            Route::get('/collections/{slug}', [StoreFrontController::class, 'collection'])->name('store.collection.show');

            Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
            Route::post('/contact/send', [MessageController::class, 'store'])->name('store.contact.send');

            // Account pages (require login on 'store' guard)
            Route::middleware(['web', 'auth:store'])->group(function () {
                Route::view('/checkout', 'store.checkout')->name('checkout');
                Route::view('/thank-you', 'store.thank-you')->name('store.thankyou');

                Route::get('/account', [AccountPagesController::class, 'account'])->name('account');

                Route::put('/account', [AccountPagesController::class, 'update'])->name('account.update');

                Route::get('/account/orders', [AccountPagesController::class, 'orders'])
                    ->name('account.orders');

                Route::get('/account/orders/{id}', function ($id) {
                    // $s is likely shared via view composer; if not, fetch StoreSetting here
                    return view('store.order-show', ['id' => $id]);
                })->name('account.order.show');

                // Customer's own orders (JSON for the account orders table)
                Route::get('/my/orders', [MyOrdersApiController::class, 'index'])
                    ->name('my_orders.index');
                // (Optional) details endpoint if you add a “view” drawer/page:
                Route::get('/my/orders/{id}', [MyOrdersApiController::class, 'show'])
                    ->name('my_orders.show');
            });

            // Auth pages (only for guests of 'store' guard)
            Route::middleware('guest:store')->group(function () {
                Route::get('/login', [StoreAuthController::class, 'showLogin'])->name('store.login.show');
                Route::post('/login', [StoreAuthController::class, 'login'])->name('store.login');

                Route::get('/register', [StoreAuthController::class, 'showRegister'])->name('store.register.show');
                Route::post('/register', [StoreAuthController::class, 'register'])->name('store.register');

            });

            // Logout (must be logged in on 'store')
            Route::post('/logout', [StoreAuthController::class, 'logout'])
                ->middleware('auth:store')->name('store.logout');

        });
    });

} else {
    // if not installed: redirect all /online_store requests to /setup
    Route::any('/online_store/{any?}', function () {
        return redirect('/setup');
    })->where('any', '.*');
}

// ------------------------------------------------------------------\\

$installed = Storage::disk('public')->exists('installed');

if ($installed === false) {
    Route::get('/setup', [
        'uses' => 'SetupController@viewCheck',
    ])->name('setup');

    Route::get('/setup/step-1', [
        'uses' => 'SetupController@viewStep1',
    ]);

    Route::post('/setup/step-2', [
        'as' => 'setupStep1', 'uses' => 'SetupController@setupStep1',
    ]);

    Route::post('/setup/testDB', [
        'as' => 'testDB', 'uses' => 'TestDbController@testDB',
    ]);

    Route::get('/setup/step-2', [
        'uses' => 'SetupController@viewStep2',
    ]);

    Route::get('/setup/step-3', [
        'uses' => 'SetupController@viewStep3',
    ]);

    Route::get('/setup/finish', function () {

        return view('setup.finishedSetup');
    });

    Route::get('/setup/getNewAppKey', [
        'as' => 'getNewAppKey', 'uses' => 'SetupController@getNewAppKey',
    ]);

    Route::get('/setup/getPassport', [
        'as' => 'getPassport', 'uses' => 'SetupController@getPassport',
    ]);

    Route::get('/setup/getMegrate', [
        'as' => 'getMegrate', 'uses' => 'SetupController@getMegrate',
    ]);

    Route::post('/setup/step-3', [
        'as' => 'setupStep2', 'uses' => 'SetupController@setupStep2',
    ]);

    Route::post('/setup/step-4', [
        'as' => 'setupStep3', 'uses' => 'SetupController@setupStep3',
    ]);

    Route::post('/setup/step-5', [
        'as' => 'setupStep4', 'uses' => 'SetupController@setupStep4',
    ]);

    Route::post('/setup/lastStep', [
        'as' => 'lastStep', 'uses' => 'SetupController@lastStep',
    ]);

    Route::get('setup/lastStep', function () {
        return redirect('/setup', 301);
    });

} else {
    Route::any('/setup/{vue}', function () {
        abort(403);
    });
}

Route::group(['middleware' => ['web', 'auth:web', 'Is_Active']], function () {

    // QuickBooks OAuth + status
    Route::get('/quickbooks/connect', [QuickBooksController::class, 'connect'])->name('quickbooks.connect');
    Route::get('/quickbooks/callback', [QuickBooksController::class, 'callback'])->name('quickbooks.callback');
});

// ------------------------------------------------------------------\\

Route::group(['middleware' => ['web', 'auth:web', 'Is_Active', 'request.safety']], function () {

    // Expose dashboard JSON to the session-authenticated SPA at /dashboard_data
    Route::get('/dashboard_data', [App\Http\Controllers\DashboardController::class, 'dashboard_data']);

    Route::get('/login', function () {
        $installed = Storage::disk('public')->exists('installed');
        if ($installed === false) {
            return redirect('/setup');
        } else {
            return redirect('/login');
        }
    });

    // Ensure legacy direct product details URL redirects to the server-rendered page
    Route::get('/products/{id}/details', function ($id) {
        return redirect('/app/products/' . $id . '/details');
    })->whereNumber('id');

    // Product details page (server-rendered) showing orders and purchases for a product
    Route::get('/app/products/{id}/details', [App\Http\Controllers\ProductsController::class, 'detailsPage'])->name('products.details');

    // Temporary debug route (no auth) to render product details without middleware checks.
    // NOTE: placed BEFORE the SPA catch-all so it can be reached for diagnostics.
    // REMOVE THIS IN PRODUCTION.
    Route::get('/debug/products/{id}/details', function ($id) {
        $salesRaw = App\Models\SaleDetail::with('product', 'sale', 'sale.client', 'sale.warehouse')
            ->where('product_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        $sales = [];
        foreach ($salesRaw as $detail) {
            $sales[] = [
                'date' => $detail->date,
                'Ref' => $detail->sale->Ref ?? '',
                'sale_id' => $detail->sale->id ?? null,
                'client_name' => $detail->sale['client']->name ?? '',
                'warehouse_name' => $detail->sale['warehouse']->name ?? '',
                'quantity' => $detail->quantity,
                'batch_no' => $detail->batch_no ?? null,
                'expiry_date' => $detail->expiry_date ?? null,
                'total' => $detail->total,
            ];
        }

        $purchasesRaw = App\Models\PurchaseDetail::with('product', 'purchase', 'purchase.provider', 'purchase.warehouse')
            ->where('product_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        $purchases = [];
        foreach ($purchasesRaw as $detail) {
            $purchases[] = [
                'date' => $detail->purchase->date ?? null,
                'Ref' => $detail->purchase->Ref ?? '',
                'purchase_id' => $detail->purchase->id ?? null,
                'provider_name' => $detail->purchase['provider']->name ?? '',
                'warehouse_name' => $detail->purchase['warehouse']->name ?? '',
                'quantity' => $detail->quantity,
                'batch_no' => $detail->batch_no ?? null,
                'expiry_date' => $detail->expiry_date ?? null,
                'total' => $detail->total,
            ];
        }

        $product = App\Models\Product::find($id);

        return view('products.details', compact('sales', 'purchases', 'product'));
    });

    Route::get('/{vue?}',
        function () {
            $installed = Storage::disk('public')->exists('installed');

            if ($installed === false) {
                return redirect('/setup');
            } else {
                return view('layouts.master');
            }
        })->where('vue', '^(?!api|setup|update|password|online_store|customer-display|quickbooks).*$');

});

// Temporary debug route (no auth) to render product details without middleware checks.
// REMOVE THIS IN PRODUCTION. Useful for diagnosing auth/middleware routing problems.
Route::get('/debug/products/{id}/details', function ($id) {
    $salesRaw = App\Models\SaleDetail::with('product', 'sale', 'sale.client', 'sale.warehouse')
        ->where('product_id', $id)
        ->orderBy('id', 'desc')
        ->get();

    $sales = [];
    foreach ($salesRaw as $detail) {
        $sales[] = [
            'date' => $detail->date,
            'Ref' => $detail->sale->Ref ?? '',
            'sale_id' => $detail->sale->id ?? null,
            'client_name' => $detail->sale['client']->name ?? '',
            'warehouse_name' => $detail->sale['warehouse']->name ?? '',
            'quantity' => $detail->quantity,
            'batch_no' => $detail->batch_no ?? null,
            'expiry_date' => $detail->expiry_date ?? null,
            'total' => $detail->total,
        ];
    }

    $purchasesRaw = App\Models\PurchaseDetail::with('product', 'purchase', 'purchase.provider', 'purchase.warehouse')
        ->where('product_id', $id)
        ->orderBy('id', 'desc')
        ->get();

    $purchases = [];
    foreach ($purchasesRaw as $detail) {
        $purchases[] = [
            'date' => $detail->purchase->date ?? null,
            'Ref' => $detail->purchase->Ref ?? '',
            'purchase_id' => $detail->purchase->id ?? null,
            'provider_name' => $detail->purchase['provider']->name ?? '',
            'warehouse_name' => $detail->purchase['warehouse']->name ?? '',
            'quantity' => $detail->quantity,
            'batch_no' => $detail->batch_no ?? null,
            'expiry_date' => $detail->expiry_date ?? null,
            'total' => $detail->total,
        ];
    }

    $product = App\Models\Product::find($id);

    return view('products.details', compact('sales', 'purchases', 'product'));
});

// Laravel 12 compatibility: define auth routes explicitly (laravel/ui optional)
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');

// Password Reset Routes...
Route::get('password/reset', 'Auth\\ForgotPasswordController@showLinkRequestForm')->name('password.request');
Route::post('password/email', 'Auth\\ForgotPasswordController@sendResetLinkEmail')->name('password.email');
Route::get('password/reset/{token}', 'Auth\\ResetPasswordController@showResetForm')->name('password.reset');
Route::post('password/reset', 'Auth\\ResetPasswordController@reset')->name('password.update');

// Email Verification Routes...
Route::get('email/verify', 'Auth\\VerificationController@show')->name('verification.notice');
Route::get('email/verify/{id}/{hash}', 'Auth\\VerificationController@verify')->name('verification.verify');
Route::post('email/resend', 'Auth\\VerificationController@resend')->name('verification.resend');

// ------------------------- -UPDATE ----------------------------------------\\

Route::group(['middleware' => ['web', 'auth:web', 'Is_Active']], function () {

    Route::get('/update', 'UpdateController@viewStep1');

    Route::get('/update/finish', function () {

        return view('update.finishedUpdate');
    });

    Route::post('/update/lastStep', [
        'as' => 'update_lastStep', 'uses' => 'UpdateController@lastStep',
    ]);

});

// -------------------- Public Customer Display (token-guarded) --------------------
// Standalone public page that mounts its own Vue app. Does not affect existing SPA.
Route::get('/customer-display', function (HttpRequest $request) {
    $token = $request->query('token');
    if (! $token || $token !== cache('customer_display_token')) {
        abort(403, 'Unauthorized display access');
    }

    return view('customer_display');
})->middleware(['web']);

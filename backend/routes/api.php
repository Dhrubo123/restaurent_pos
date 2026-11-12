<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\CashierShiftController;
use App\Http\Controllers\Api\DiningTableActivationController;
use App\Http\Controllers\Api\DiningTableController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\KotController;
use App\Http\Controllers\Api\MenuCategoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\MenuSubcategoryController;
use App\Http\Controllers\Api\ModifierGroupController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderItemController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ZoneActivationController;
use App\Http\Controllers\Api\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('web')->group(function () {
    Route::get('/csrf-token', [AuthController::class, 'csrf'])->middleware('throttle:30,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/floor', FloorController::class)->middleware('can:view-floor');
        Route::get('/menu', MenuController::class);
        Route::post('/menu/items/{menuItem}/availability', [MenuItemController::class, 'updateAvailability']);

        Route::get('/kots', [KotController::class, 'index']);
        Route::post('/kots/{kot}/start', [KotController::class, 'start']);
        Route::post('/kots/{kot}/ready', [KotController::class, 'ready']);
        Route::post('/kots/{kot}/reprint', [KotController::class, 'reprint']);
        Route::post('/kot-items/{kotItem}/served', [KotController::class, 'serveItem']);

        Route::post('/orders/{order}/discounts', [BillingController::class, 'applyDiscount']);
        Route::delete('/orders/{order}/discounts', [BillingController::class, 'removeDiscount']);
        Route::post('/orders/{order}/discount', [BillingController::class, 'applyDiscount']);
        Route::delete('/orders/{order}/discount', [BillingController::class, 'removeDiscount']);

        Route::get('/invoices', [BillingController::class, 'invoices']);
        Route::post('/invoices/{invoice}/void', [BillingController::class, 'voidInvoice']);
        Route::post('/invoices/{invoice}/refunds', [BillingController::class, 'refundInvoice']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);
        Route::get('/reports/export', [ReportController::class, 'exportCsv']);
        Route::get('/audit-events', [ReportController::class, 'auditEvents']);

        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory/items', [InventoryController::class, 'storeItem']);
        Route::patch('/inventory/items/{inventoryItem}', [InventoryController::class, 'updateItem']);
        Route::post('/inventory/items/{inventoryItem}/adjust', [InventoryController::class, 'adjustStock']);
        Route::post('/inventory/suppliers', [InventoryController::class, 'storeSupplier']);
        Route::post('/inventory/purchases', [InventoryController::class, 'recordPurchase']);
        Route::put('/inventory/recipes/{menuItem}', [InventoryController::class, 'syncRecipe']);

        Route::get('/staff', [StaffController::class, 'index']);
        Route::post('/staff', [StaffController::class, 'store']);
        Route::patch('/staff/{staff}', [StaffController::class, 'update']);
        Route::post('/staff/{staff}/reset-password', [StaffController::class, 'resetPassword']);
        Route::post('/staff/{staff}/activation', [StaffController::class, 'activate']);
        Route::delete('/staff/{staff}/activation', [StaffController::class, 'deactivate']);

        Route::middleware('can:take-payment')->group(function () {
            Route::get('/shifts/current', [CashierShiftController::class, 'current']);
            Route::post('/shifts/open', [CashierShiftController::class, 'open']);
            Route::post('/shifts/{cashierShift}/close', [CashierShiftController::class, 'close']);
            Route::post('/shifts/{cashierShift}/approve', [CashierShiftController::class, 'approve']);

            Route::post('/orders/{order}/payments', [BillingController::class, 'recordPayment']);
            Route::post('/orders/{order}/checkout', [BillingController::class, 'checkout']);

            Route::post('/invoices/{invoice}/reprint', [BillingController::class, 'reprintInvoice']);
        });

        Route::middleware('can:manage-orders')->group(function () {
            Route::get('/orders', [OrderController::class, 'index']);
            Route::post('/tables/{diningTable}/orders', [OrderController::class, 'openForTable']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::patch('/orders/{order}', [OrderController::class, 'update']);
            Route::post('/orders/{order}/send', [KotController::class, 'sendRound']);
            Route::post('/orders/{order}/request-bill', [OrderController::class, 'requestBill']);
            Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
            Route::post('/orders/{order}/transfer', [OrderController::class, 'transfer']);
            Route::post('/orders/{order}/merge', [OrderController::class, 'merge']);

            Route::post('/orders/{order}/items', [OrderItemController::class, 'store']);
            Route::patch('/orders/{order}/items/{orderItem}', [OrderItemController::class, 'update']);
            Route::delete('/orders/{order}/items/{orderItem}', [OrderItemController::class, 'destroy']);
            Route::post('/orders/{order}/items/{orderItem}/cancel', [OrderItemController::class, 'cancelSent']);
        });

        Route::middleware('can:manage-floor')->group(function () {
            Route::get('/zones', [ZoneController::class, 'index']);
            Route::post('/zones', [ZoneController::class, 'store']);
            Route::patch('/zones/{zone}', [ZoneController::class, 'update']);
            Route::post('/zones/{zone}/activation', [ZoneActivationController::class, 'store']);
            Route::delete('/zones/{zone}/activation', [ZoneActivationController::class, 'destroy']);

            Route::post('/tables', [DiningTableController::class, 'store']);
            Route::patch('/tables/{diningTable}', [DiningTableController::class, 'update']);
            Route::post('/tables/{diningTable}/activation', [DiningTableActivationController::class, 'store']);
            Route::delete('/tables/{diningTable}/activation', [DiningTableActivationController::class, 'destroy']);
        });

        Route::middleware('can:manage-menu')->group(function () {
            Route::post('/menu/categories', [MenuCategoryController::class, 'store']);
            Route::patch('/menu/categories/{menuCategory}', [MenuCategoryController::class, 'update']);
            Route::post('/menu/categories/{menuCategory}/activation', [MenuCategoryController::class, 'activate']);
            Route::delete('/menu/categories/{menuCategory}/activation', [MenuCategoryController::class, 'deactivate']);

            Route::post('/menu/subcategories', [MenuSubcategoryController::class, 'store']);
            Route::patch('/menu/subcategories/{menuSubcategory}', [MenuSubcategoryController::class, 'update']);
            Route::post('/menu/subcategories/{menuSubcategory}/activation', [MenuSubcategoryController::class, 'activate']);
            Route::delete('/menu/subcategories/{menuSubcategory}/activation', [MenuSubcategoryController::class, 'deactivate']);

            Route::post('/menu/items', [MenuItemController::class, 'store']);
            Route::patch('/menu/items/{menuItem}', [MenuItemController::class, 'update']);
            Route::post('/menu/items/{menuItem}/activation', [MenuItemController::class, 'activate']);
            Route::delete('/menu/items/{menuItem}/activation', [MenuItemController::class, 'deactivate']);
            Route::post('/menu/tax-categories', [MenuItemController::class, 'storeTaxCategory']);
            Route::post('/menu/tax-mode', [MenuItemController::class, 'applyBulkTaxCategory']);

            Route::post('/menu/modifier-groups', [ModifierGroupController::class, 'store']);
            Route::patch('/menu/modifier-groups/{modifierGroup}', [ModifierGroupController::class, 'update']);
            Route::post('/menu/modifier-groups/{modifierGroup}/activation', [ModifierGroupController::class, 'activate']);
            Route::delete('/menu/modifier-groups/{modifierGroup}/activation', [ModifierGroupController::class, 'deactivate']);
        });
    });
});

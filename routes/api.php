<?php

use App\Http\Controllers\Api\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| Per FRS: Public Room Search + Room Detail require no auth. Contact
| info is visible on room detail by design (see BUSINESS_RULES_CHECK
| notes) - guests see the same room data a logged-in student would.
*/
Route::post('/auth/register/student', [AuthController::class, 'registerStudent']);
Route::post('/auth/register/landlord', [AuthController::class, 'registerLandlord']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Authenticated routes (any role)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    /*
    |----------------------------------------------------------------
    | Landlord-only
    |----------------------------------------------------------------
    */
    Route::middleware('role:landlord')->group(function () {
        Route::get('/my-rooms', [RoomController::class, 'mine']);
        Route::post('/rooms', [RoomController::class, 'store']);
    });

    /*
    |----------------------------------------------------------------
    | Landlord (own room) or Admin (any room) - ownership/role is
    | resolved by RoomPolicy::update/delete inside the controller,
    | per the permission matrix in docs/BACKEND_ARCHITECTURE.md #5.
    |----------------------------------------------------------------
    */
    Route::middleware('role:landlord,admin')->group(function () {
        Route::put('/rooms/{room}', [RoomController::class, 'update']);
        Route::delete('/rooms/{room}', [RoomController::class, 'destroy']);
    });

    /*
    |----------------------------------------------------------------
    | Admin-only
    |----------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/rooms/pending', [AdminRoomController::class, 'pending']);
        Route::get('/rooms', [AdminRoomController::class, 'index']);
        Route::post('/rooms/{room}/approve', [AdminRoomController::class, 'approve']);
        Route::post('/rooms/{room}/reject', [AdminRoomController::class, 'reject']);
        Route::delete('/rooms/{room}/force', [AdminRoomController::class, 'forceDestroy']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users/admins', [AdminUserController::class, 'storeAdmin']);
        Route::post('/users/{user}/disable', [AdminUserController::class, 'disable']);
        Route::post('/users/{user}/enable', [AdminUserController::class, 'enable']);
    });
});

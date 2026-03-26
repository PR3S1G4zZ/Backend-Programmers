<?php

use App\Http\Controllers\{
    AuthController,
    ProjectController,
    ApplicationController,
    DashboardController,
    AdminController,
    ConversationController,
    DeveloperController,
    ProfileController,
    TaxonomyController,
    PaymentMethodController,
    WalletController,
    FavoriteController,
    ReviewController,
    BackupController
};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    // Registro y Login
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login',    [AuthController::class, 'login'])->middleware('throttle:6,1');
    
    // Google Auth
    Route::get('/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);

    // GitHub Auth
    Route::get('/github', [AuthController::class, 'redirectToGithub']);
    Route::get('/github/callback', [AuthController::class, 'handleGithubCallback']);

    // Verificación de vinculación de cuenta social
    Route::get('/verify-social-link', [AuthController::class, 'verifySocialLink']);

    // Verificación de email
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);

    // recuperar contraseña
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/send-verification-email', [AuthController::class, 'sendVerificationEmail']);
    });
});


/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS (PROYECTOS, APLICACIONES, DASHBOARD)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

     // Projects (solo empresas)
     Route::get('/projects', [ProjectController::class, 'index']);
     Route::post('/projects', [ProjectController::class, 'store'])->middleware('throttle:10,1');
     Route::get('/projects/{project}', [ProjectController::class, 'show']);
     Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware('throttle:20,1');
     Route::post('/projects/{project}/fund', [ProjectController::class, 'fund'])->middleware('throttle:5,1');
     Route::post('/projects/{project}/start', [ProjectController::class, 'start'])->middleware('throttle:10,1');
     Route::get('/projects/{project}/developer-progress', [ProjectController::class, 'getDeveloperProgress']);
     Route::put('/projects/{project}/developer-progress/{developerId}', [ProjectController::class, 'updateDeveloperProgress'])->middleware('throttle:30,1');
     Route::post('/projects/{project}/complete', [ProjectController::class, 'complete'])->middleware('throttle:5,1');
     Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('throttle:10,1');

    // Aplicaciones (programadores)
    Route::post('/projects/{project}/apply', [ApplicationController::class, 'apply'])->middleware('throttle:10,1');
    Route::get('/applications/mine', [ApplicationController::class, 'myApplications']);
    
    // Gestión de Candidatos (Empresa)
    Route::get('/projects/{project}/applications', [ApplicationController::class, 'index']);
    Route::post('/applications/{application}/accept', [ApplicationController::class, 'accept'])->middleware('throttle:10,1');
    Route::post('/applications/{application}/reject', [ApplicationController::class, 'reject'])->middleware('throttle:10,1');

    // Wallet
    Route::get('/wallet', [\App\Http\Controllers\WalletController::class, 'show']);
    Route::post('/wallet/recharge', [\App\Http\Controllers\WalletController::class, 'recharge'])->middleware('throttle:10,1');
    Route::post('/wallet/withdraw', [\App\Http\Controllers\WalletController::class, 'withdraw'])->middleware('throttle:10,1');

    // Dashboards
    Route::get('/dashboard/company', [DashboardController::class, 'company']);
    Route::get('/dashboard/programmer', [DashboardController::class, 'programmer']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::match(['put', 'post'], '/profile', [ProfileController::class, 'update']);
    
    // Portfolio Projects
    Route::apiResource('portfolio-projects', \App\Http\Controllers\PortfolioProjectController::class);

    // Taxonomies
    Route::get('/taxonomies/skills', [TaxonomyController::class, 'skills']);
    Route::get('/taxonomies/categories', [TaxonomyController::class, 'categories']);

    // Developers
    Route::get('/developers', [DeveloperController::class, 'index']);
    Route::get('/developers/{id}', [DeveloperController::class, 'show']);
    
    // Developer: Mis proyectos completados
    Route::get('/developer/completed-projects', [DeveloperController::class, 'myCompletedProjects']);

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])->middleware('throttle:30,1');

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites', [FavoriteController::class, 'store'])->middleware('throttle:20,1');

    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::post('/reviews', [ReviewController::class, 'store'])->middleware('throttle:5,1');
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
    Route::get('/projects/{project}/reviews', [ReviewController::class, 'projectReviews']);

    // Company projects
    Route::get('/company/projects', [ProjectController::class, 'companyProjects']);

    // Admin routes (solo administradores)
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/users/stats', [AdminController::class, 'getUserStats']);
        Route::get('/users/{id}', [AdminController::class, 'getUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);
        Route::post('/users/{id}/restore', [AdminController::class, 'restoreUser']);
        Route::post('/users/{id}/ban', [AdminController::class, 'banUser']);
        
        // Admin Projects
        Route::get('/projects', [AdminController::class, 'getProjects']);
        Route::put('/projects/{id}', [AdminController::class, 'updateProject']);
        Route::delete('/projects/{id}', [AdminController::class, 'deleteProject']);
        Route::post('/projects/{id}/restore', [AdminController::class, 'restoreProject']);

        Route::get('/metrics', [AdminController::class, 'metrics']);

        // Commissions
        Route::get('/commissions', [AdminController::class, 'commissions']);
        Route::get('/commissions/stats', [AdminController::class, 'commissionStats']);

        // Categories Management
        Route::apiResource('categories', \App\Http\Controllers\CategoryController::class)->except(['index', 'show']);

        // System Settings & Logs
        Route::get('/system/settings', [\App\Http\Controllers\SettingsController::class, 'getSystemSettings']);
        Route::put('/system/settings', [\App\Http\Controllers\SettingsController::class, 'updateSystemSettings']);
        Route::get('/system/logs', [\App\Http\Controllers\SettingsController::class, 'getActivityLogs']);

        // Database Backups
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/create', [BackupController::class, 'create']);
            Route::post('/restore', [BackupController::class, 'restore']);
            Route::get('/download/{filename}', [BackupController::class, 'download']);
        });
    });

    // Milestones
    Route::get('/projects/{project}/milestones', [\App\Http\Controllers\MilestoneController::class, 'index']);
    Route::post('/projects/{project}/milestones', [\App\Http\Controllers\MilestoneController::class, 'store']);
    Route::put('/projects/{project}/milestones/{milestone}', [\App\Http\Controllers\MilestoneController::class, 'update']);
    Route::delete('/projects/{project}/milestones/{milestone}', [\App\Http\Controllers\MilestoneController::class, 'destroy']);
    Route::post('/projects/{project}/milestones/{milestone}/submit', [\App\Http\Controllers\MilestoneController::class, 'submit']);
    Route::post('/projects/{project}/milestones/{milestone}/approve', [\App\Http\Controllers\MilestoneController::class, 'approve']);
    Route::post('/projects/{project}/milestones/{milestone}/reject', [\App\Http\Controllers\MilestoneController::class, 'reject']);


    // Payment Methods
    Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->middleware('throttle:10,1');
    Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);

    // --- Settings & Preferences ---
    Route::get('/preferences', [\App\Http\Controllers\SettingsController::class, 'getPreferences']);
    Route::put('/preferences', [\App\Http\Controllers\SettingsController::class, 'updatePreferences']);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy']);
    Route::delete('/notifications/clear-read', [\App\Http\Controllers\NotificationController::class, 'clearRead']);
    
});

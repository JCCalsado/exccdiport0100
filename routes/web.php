<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StudentDashboardController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AccountingDashboardController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\CurriculaController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Student-specific routes
Route::middleware(['auth', 'verified', 'role:student'])->prefix('student')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/account', [StudentAccountController::class, 'index'])->name('student.account');
    Route::get('/payment', [PaymentController::class, 'create'])->name('payment.create');
    Route::get('/my-profile', [StudentController::class, 'studentProfile'])->name('my-profile');
});

// Student Archive routes (for admin/accounting)
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::resource('students', StudentController::class);
    Route::post('students/{student}/payments', [StudentController::class, 'storePayment'])->name('students.payments.store');
});

// Student Fee Management routes
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->prefix('student-fees')->group(function () {
    Route::get('/', [StudentFeeController::class, 'index'])->name('student-fees.index');
    
    // Create new student (separate from assessment)
    Route::get('/create-student', [StudentFeeController::class, 'createStudent'])->name('student-fees.create-student');
    Route::post('/store-student', [StudentFeeController::class, 'storeStudent'])->name('student-fees.store-student');
    
    // Create assessment
    Route::get('/create', [StudentFeeController::class, 'create'])->name('student-fees.create');
    Route::post('/', [StudentFeeController::class, 'store'])->name('student-fees.store');
    
    // View and manage specific student
    Route::get('/{user}', [StudentFeeController::class, 'show'])->name('student-fees.show');
    Route::get('/{user}/edit', [StudentFeeController::class, 'edit'])->name('student-fees.edit');
    Route::put('/{user}', [StudentFeeController::class, 'update'])->name('student-fees.update');
    
    // Payment for student
    Route::post('/{user}/payments', [StudentFeeController::class, 'storePayment'])->name('student-fees.payments.store');
    
    // Export PDF
    Route::get('/{user}/export-pdf', [StudentFeeController::class, 'exportPdf'])->name('student-fees.export-pdf');

    // OBE Curriculum routes
    Route::get('/curriculum/terms/{program}', [StudentFeeController::class, 'getAvailableTerms'])
        ->name('student-fees.curriculum.terms');
    Route::post('/curriculum/preview', [StudentFeeController::class, 'getCurriculumPreview'])
        ->name('student-fees.curriculum.preview');
});

// Curriculum Management routes (admin/accounting only)
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    // Use resource controller (this creates all CRUD routes)
    Route::resource('curricula', CurriculaController::class);
    
    // Additional custom routes
    Route::post('curricula/{curriculum}/toggle-status', [CurriculaController::class, 'toggleStatus'])
        ->name('curricula.toggleStatus');
    Route::get('curricula/ajax/courses', [CurriculaController::class, 'getCourses'])
        ->name('curricula.get-courses');

    Route::get('curricula/ajax/courses', [CurriculaController::class, 'getCourses'])
        ->name('curricula.get-courses');
});

// Transaction routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/download', [TransactionController::class, 'download'])->name('transactions.download');
    Route::post('/account/pay-now', [TransactionController::class, 'payNow'])->name('account.pay-now');
});

Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
});

// Admin routes
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
});

// Accounting routes
Route::middleware(['auth', 'verified', 'role:accounting,admin'])->prefix('accounting')->group(function () {
    Route::get('/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
    Route::get('/transactions', [TransactionController::class, 'index'])->name('accounting.transactions.index');
});

// Fee Management routes
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::resource('fees', FeeController::class);
    Route::post('fees/{fee}/toggle-status', [FeeController::class, 'toggleStatus'])->name('fees.toggleStatus');
    Route::post('fees/assign-to-students', [FeeController::class, 'assignToStudents'])->name('fees.assignToStudents');
});

// Subject Management routes
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::resource('subjects', SubjectController::class);
    Route::post('subjects/{subject}/enroll-students', [SubjectController::class, 'enrollStudents'])->name('subjects.enrollStudents');
});

// User Management routes (admin only)
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::resource('users', UserController::class);
});

// Notification routes
Route::middleware(['auth', 'verified', 'role:admin,accounting'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
});

// Settings routes
Route::middleware('auth')->prefix('settings')->name('profile.')->group(function () {
    Route::delete('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'destroy'])->name('destroy');
    Route::get('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'edit'])->name('edit');
    Route::patch('profile', [\App\Http\Controllers\Settings\ProfileController::class, 'update'])->name('update');
    Route::post('profile-picture', [\App\Http\Controllers\Settings\ProfileController::class, 'updatePicture'])->name('update-picture');
    Route::delete('profile-picture', [\App\Http\Controllers\Settings\ProfileController::class, 'removePicture'])->name('remove-picture');
});

Route::middleware('auth')->prefix('settings')->name('password.')->group(function () {
    Route::get('password', [\App\Http\Controllers\Settings\PasswordController::class, 'edit'])->name('edit');
    Route::put('password', [\App\Http\Controllers\Settings\PasswordController::class, 'update'])->name('update');
});

Route::middleware('auth')->prefix('settings')->group(function () {
    Route::get('appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
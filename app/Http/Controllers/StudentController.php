<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use App\Models\StudentAssessment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Services\AccountService;

class StudentController extends Controller
{
    /**
     * ✅ Display all students (for admin/accounting archives)
     * PRIMARY IDENTIFIER: account_id
     */
    public function index(Request $request)
    {
        $query = Student::with(['user', 'account'])
            ->whereNotNull('account_id'); // ✅ CRITICAL: Only students with account_id

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_id', 'like', "%{$search}%") // ✅ Search by account_id
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$search}%"])
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name) like ?", ["%{$search}%"]);
            });
        }

        // Course filter
        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        // Year level filter
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Balance filter
        if ($request->filled('has_balance')) {
            if ($request->boolean('has_balance')) {
                $query->where('total_balance', '>', 0);
            } else {
                $query->where('total_balance', '<=', 0);
            }
        }

        $students = $query->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'status' => $student->status,
                    'total_balance' => (float) ($student->total_balance ?? 0),
                    'remaining_balance' => (float) $student->remaining_balance,
                    'user' => $student->user ? [
                        'id' => $student->user->id,
                        'status' => $student->user->status,
                    ] : null,
                ];
            });

        // Get filter options
        $courses = Student::whereNotNull('course')
            ->whereNotNull('account_id')
            ->distinct()
            ->orderBy('course')
            ->pluck('course');

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        
        $statuses = [
            'enrolled' => 'Enrolled',
            'graduated' => 'Graduated',
            'inactive' => 'Inactive',
        ];

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status', 'has_balance']),
            'courses' => $courses,
            'yearLevels' => $yearLevels,
            'statuses' => $statuses,
        ]);
    }

    /**
     * ✅ Show student profile by account_id
     * PRIMARY IDENTIFIER: account_id
     */
    public function show(string $accountId)
    {
        // ✅ CRITICAL: Find by account_id, not id
        $student = Student::with([
                'user.account',
                'payments' => fn($q) => $q->latest('paid_at')->take(10),
                'paymentTerms' => fn($q) => $q->orderBy('term_order'),
                'assessments' => fn($q) => $q->latest()->take(5),
                'transactions' => fn($q) => $q->latest('created_at')->take(10),
            ])
            ->where('account_id', $accountId)
            ->firstOrFail();

        // Calculate financial summary
        $totalScheduled = $student->paymentTerms->sum('amount');
        $totalPaid = $student->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        $remainingDue = max(0, $totalScheduled - $totalPaid);

        // Get latest assessment
        $latestAssessment = $student->assessments()
            ->where('status', 'active')
            ->with('curriculum.program')
            ->latest()
            ->first();

        return Inertia::render('Students/Show', [
            'student' => [
                'id' => $student->id,
                'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
                'status' => $student->status,
                'birthday' => $student->birthday?->format('Y-m-d'),
                'phone' => $student->phone,
                'address' => $student->address,
                'total_balance' => (float) ($student->total_balance ?? 0),
                'remaining_balance' => (float) $student->remaining_balance,
                'user' => $student->user ? [
                    'id' => $student->user->id,
                    'email' => $student->user->email,
                    'status' => $student->user->status,
                    'role' => $student->user->role,
                ] : null,
            ],
            'account' => $student->user?->account ? [
                'id' => $student->user->account->id,
                'balance' => (float) $student->user->account->balance,
                'created_at' => $student->user->account->created_at?->toISOString(),
                'updated_at' => $student->user->account->updated_at?->toISOString(),
            ] : null,
            'latestAssessment' => $latestAssessment ? [
                'id' => $latestAssessment->id,
                'assessment_number' => $latestAssessment->assessment_number,
                'school_year' => $latestAssessment->school_year,
                'semester' => $latestAssessment->semester,
                'total_assessment' => (float) $latestAssessment->total_assessment,
                'status' => $latestAssessment->status,
                'curriculum' => $latestAssessment->curriculum ? [
                    'program' => $latestAssessment->curriculum->program->full_name ?? 'N/A',
                ] : null,
            ] : null,
            'payments' => $student->payments->map(fn($payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'description' => $payment->description,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ]),
            'paymentTerms' => $student->paymentTerms->map(fn($term) => [
                'id' => $term->id,
                'term_name' => $term->term_name,
                'term_order' => $term->term_order,
                'amount' => (float) $term->amount,
                'paid_amount' => (float) $term->paid_amount,
                'remaining_balance' => (float) $term->remaining_balance,
                'due_date' => $term->due_date?->format('Y-m-d'),
                'status' => $term->status,
                'is_overdue' => $term->isOverdue(),
            ]),
            'transactions' => $student->transactions->map(fn($txn) => [
                'id' => $txn->id,
                'reference' => $txn->reference,
                'kind' => $txn->kind,
                'type' => $txn->type,
                'amount' => (float) $txn->amount,
                'status' => $txn->status,
                'payment_channel' => $txn->payment_channel,
                'paid_at' => $txn->paid_at?->toISOString(),
                'created_at' => $txn->created_at->toISOString(),
            ]),
            'stats' => [
                'total_scheduled' => (float) $totalScheduled,
                'total_paid' => (float) $totalPaid,
                'remaining_due' => (float) $remainingDue,
                'payment_count' => $student->payments()->count(),
                'pending_terms' => $student->paymentTerms()->where('status', 'pending')->count(),
                'overdue_terms' => $student->paymentTerms()->overdue()->count(),
            ],
        ]);
    }

    /**
     * ✅ Student profile (for logged-in student)
     * Redirects to unified show method using account_id
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // ✅ Get student record by user_id, then redirect using account_id
        $student = Student::where('user_id', $user->id)->firstOrFail();

        if (!$student->account_id) {
            Log::error('Student missing account_id', [
                'user_id' => $user->id,
                'student_id' => $student->id,
            ]);
            
            return back()->withErrors([
                'error' => 'Your account is not properly configured. Please contact the registrar.'
            ]);
        }

        // ✅ Redirect to unified show method using account_id
        return redirect()->route('students.show', $student->account_id);
    }

    /**
     * ✅ Edit student by account_id
     */
    public function edit(string $accountId)
    {
        $student = Student::with('user')
            ->where('account_id', $accountId)
            ->firstOrFail();

        $courses = collect([
            'BS Electrical Engineering Technology',
            'BS Electronics Engineering Technology',
            'BS Computer Science',
            'BS Information Technology',
            'BS Accountancy',
        ]);

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        
        $statuses = [
            'enrolled' => 'Enrolled',
            'graduated' => 'Graduated',
            'inactive' => 'Inactive',
        ];

        return Inertia::render('Students/Edit', [
            'student' => [
                'id' => $student->id,
                'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                'student_id' => $student->student_id,
                'last_name' => $student->last_name,
                'first_name' => $student->first_name,
                'middle_initial' => $student->middle_initial,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
                'status' => $student->status,
                'birthday' => $student->birthday?->format('Y-m-d'),
                'phone' => $student->phone,
                'address' => $student->address,
                'total_balance' => (float) $student->total_balance,
            ],
            'courses' => $courses,
            'yearLevels' => $yearLevels,
            'statuses' => $statuses,
        ]);
    }

    /**
     * ✅ Update student by account_id
     */
    public function update(Request $request, string $accountId)
    {
        $student = Student::with('user')
            ->where('account_id', $accountId)
            ->firstOrFail();

        $validated = $request->validate([
            // ✅ CRITICAL: account_id cannot be changed
            'account_id' => [
                'prohibited', // User cannot modify account_id
            ],
            'student_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('students', 'student_id')->ignore($student->id),
            ],
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => [
                'required',
                'email',
                Rule::unique('students', 'email')->ignore($student->id),
            ],
            'course' => 'required|string|max:255',
            'year_level' => 'required|string|in:1st Year,2nd Year,3rd Year,4th Year',
            'status' => 'required|string|in:enrolled,graduated,inactive',
            'birthday' => 'nullable|date|before:today|after:1900-01-01',
            'phone' => 'nullable|string|max:20|regex:/^[0-9\s\-\+\(\)]*$/',
            'address' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Update student record
            $student->update([
                'student_id' => $validated['student_id'],
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'course' => $validated['course'],
                'year_level' => $validated['year_level'],
                'status' => $validated['status'],
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
            ]);

            // Update associated user if exists
            if ($student->user) {
                $student->user->update([
                    'last_name' => $validated['last_name'],
                    'first_name' => $validated['first_name'],
                    'middle_initial' => $validated['middle_initial'],
                    'email' => $validated['email'],
                    'course' => $validated['course'],
                    'year_level' => $validated['year_level'],
                    'status' => $this->mapStudentStatusToUserStatus($validated['status']),
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                    'birthday' => $validated['birthday'],
                ]);
            }

            DB::commit();

            Log::info('Student updated successfully', [
                'account_id' => $accountId,
                'updated_by' => $request->user()->id,
            ]);

            return redirect()
                ->route('students.show', $student->account_id) // ✅ USE account_id
                ->with('success', 'Student updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Student update failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to update student: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * ✅ Store payment for a student by account_id
     */
    public function storePayment(Request $request, string $accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $balance = abs($student->user?->account->balance ?? $student->total_balance ?? 0);

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                "max:{$balance}",
            ],
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'description' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'payment_date' => 'required|date|before_or_equal:today',
        ]);

        DB::beginTransaction();
        try {
            $paymentDate = $validated['payment_date'] ?? now();

            // ✅ Create Payment record - PRIMARY LINKAGE: account_id
            $payment = Payment::create([
                'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                'student_id' => $student->id, // Keep for backward compatibility
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? 'PAY-' . strtoupper(\Illuminate\Support\Str::random(10)),
                'description' => $validated['description'] ?? 'Payment',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $paymentDate,
            ]);

            // ✅ Create Transaction using account_id
            Transaction::create([
                'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                'user_id' => $student->user_id ?? null, // Keep for backward compatibility
                'reference' => $payment->reference_number,
                'payment_channel' => $validated['payment_method'],
                'kind' => 'payment',
                'type' => 'Payment',
                'amount' => $validated['amount'],
                'status' => 'paid',
                'paid_at' => $paymentDate,
                'meta' => [
                    'payment_id' => $payment->id,
                    'description' => $validated['description'] ?? 'Payment',
                ],
            ]);

            // Recalculate account balance
            if ($student->user) {
                AccountService::recalculate($student->user);
            }

            DB::commit();

            Log::info('Payment recorded successfully', [
                'account_id' => $accountId,
                'amount' => $validated['amount'],
                'payment_id' => $payment->id,
            ]);

            return back()->with('success', 'Payment recorded successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Payment recording failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again.'
            ]);
        }
    }

    /**
     * ✅ Delete student by account_id (soft delete - deactivate only)
     */
    public function destroy(string $accountId)
    {
        $student = Student::where('account_id', $accountId)->firstOrFail();

        // ✅ CRITICAL: Do NOT delete if student has balance
        if ($student->remaining_balance > 0) {
            return back()->withErrors([
                'error' => 'Cannot delete student with outstanding balance. Please clear balance first.'
            ]);
        }

        // ✅ CRITICAL: Do NOT delete if student has recent transactions
        $recentTransactions = $student->transactions()
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        if ($recentTransactions > 0) {
            return back()->withErrors([
                'error' => 'Cannot delete student with recent transactions. Please contact administrator.'
            ]);
        }

        DB::beginTransaction();
        try {
            // ✅ Deactivate instead of delete
            $student->update(['status' => 'inactive']);
            
            if ($student->user) {
                $student->user->update(['status' => User::STATUS_DROPPED]);
            }

            DB::commit();

            Log::info('Student deactivated', [
                'account_id' => $accountId,
                'deactivated_by' => auth()->id(),
            ]);

            return redirect()
                ->route('students.index')
                ->with('success', 'Student deactivated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Student deactivation failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to deactivate student.'
            ]);
        }
    }

    /**
     * ✅ Helper: Map student status to user status
     */
    protected function mapStudentStatusToUserStatus(string $studentStatus): string
    {
        return match($studentStatus) {
            'enrolled' => User::STATUS_ACTIVE,
            'graduated' => User::STATUS_GRADUATED,
            'inactive' => User::STATUS_DROPPED,
            default => User::STATUS_ACTIVE,
        };
    }

    /**
     * ✅ Helper: Map user status to student status
     */
    protected function mapUserStatusToStudentStatus(string $userStatus): string
    {
        return match($userStatus) {
            User::STATUS_ACTIVE => 'enrolled',
            User::STATUS_GRADUATED => 'graduated',
            User::STATUS_DROPPED => 'inactive',
            default => 'enrolled',
        };
    }
}
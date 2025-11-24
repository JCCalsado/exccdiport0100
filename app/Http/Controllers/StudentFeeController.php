<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\CurriculumService;
use App\Models\Program;
use App\Models\Curriculum;

class StudentFeeController extends Controller
{
    /**
     * Display listing of students for fee management
     */
    public function index(Request $request)
    {
        $query = User::with(['student', 'account'])
            ->where('role', 'student');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        // Filter by course
        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        // Filter by year level
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->paginate(15)->withQueryString();

        // Get filter options
        $courses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course');

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $statuses = [
            User::STATUS_ACTIVE => 'Active',
            User::STATUS_GRADUATED => 'Graduated',
            User::STATUS_DROPPED => 'Dropped',
        ];

        return Inertia::render('StudentFees/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status']),
            'courses' => $courses,
            'yearLevels' => $yearLevels,
            'statuses' => $statuses,
        ]);
    }

    protected $curriculumService;

    // Add to constructor
    public function __construct(CurriculumService $curriculumService)
    {
        $this->curriculumService = $curriculumService;
    }
    /**
     * Show create assessment form
     */
    public function create(Request $request)
    {
        // If this is an AJAX request for getting student data
        if ($request->has('get_data') && $request->has('student_id')) {
            $student = User::where('role', 'student')->findOrFail($request->student_id);
            
            // Get subjects for this student
            $subjects = Subject::active()
                ->where('course', $student->course)
                ->where('year_level', $student->year_level)
                ->get()
                ->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'code' => $subject->code,
                        'name' => $subject->name,
                        'units' => $subject->units,
                        'price_per_unit' => $subject->price_per_unit,
                        'has_lab' => $subject->has_lab,
                        'lab_fee' => $subject->lab_fee,
                        'total_cost' => $subject->total_cost,
                    ];
                });

            // Get fees
            $fees = Fee::active()
                ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
                ->get()
                ->map(function ($fee) {
                    return [
                        'id' => $fee->id,
                        'name' => $fee->name,
                        'category' => $fee->category,
                        'amount' => $fee->amount,
                    ];
                });

            return response()->json([
                'subjects' => $subjects,
                'fees' => $fees,
            ]);
        }

        // Get all active students for selection
        $students = User::where('role', 'student')
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'student_id' => $user->student_id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'course' => $user->course,
                    'year_level' => $user->year_level,
                    'status' => $user->status,
                ];
            });

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        $currentYear = now()->year;
        $schoolYears = [
            "{$currentYear}-" . ($currentYear + 1),
            ($currentYear - 1) . "-{$currentYear}",
        ];

        return Inertia::render('StudentFees/Create', [
            'students' => $students,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Store new assessment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'subjects' => 'required|array|min:1',
            'subjects.*.id' => 'required|exists:subjects,id',
            'subjects.*.units' => 'required|numeric|min:0',
            'subjects.*.amount' => 'required|numeric|min:0',
            'other_fees' => 'nullable|array',
            'other_fees.*.id' => 'required|exists:fees,id',
            'other_fees.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Calculate tuition fee
            $tuitionFee = collect($validated['subjects'])->sum('amount');
            
            // Calculate other fees
            $otherFeesTotal = isset($validated['other_fees']) 
                ? collect($validated['other_fees'])->sum('amount') 
                : 0;

            // Create assessment
            $assessment = StudentAssessment::create([
                'user_id' => $validated['user_id'],
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'subjects' => $validated['subjects'],
                'fee_breakdown' => $validated['other_fees'] ?? [],
                'created_by' => auth()->id(),
                'status' => 'active',
            ]);

            // Create transactions for each subject
            foreach ($validated['subjects'] as $subject) {
                Transaction::create([
                    'user_id' => $validated['user_id'],
                    'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                    'kind' => 'charge',
                    'type' => 'Tuition',
                    'year' => explode('-', $validated['school_year'])[0],
                    'semester' => $validated['semester'],
                    'amount' => $subject['amount'],
                    'status' => 'pending',
                    'meta' => [
                        'assessment_id' => $assessment->id,
                        'subject_id' => $subject['id'],
                        'description' => 'Tuition Fee - Subject',
                    ],
                ]);
            }

            // Create transactions for other fees
            if (isset($validated['other_fees'])) {
                foreach ($validated['other_fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'user_id' => $validated['user_id'],
                        'fee_id' => $fee['id'],
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'type' => $feeModel->category,
                        'year' => explode('-', $validated['school_year'])[0],
                        'semester' => $validated['semester'],
                        'amount' => $fee['amount'],
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'fee_code' => $feeModel->code,
                            'fee_name' => $feeModel->name,
                        ],
                    ]);
                }
            }

            // Recalculate student balance
            $user = User::find($validated['user_id']);
            \App\Services\AccountService::recalculate($user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $validated['user_id'])
                ->with('success', 'Student fee assessment created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create assessment: ' . $e->getMessage()]);
        }
    }

    /**
     * Show student fee details
     */
    public function show($userId)
{
    // Load student + related models
    $student = User::with(['student', 'account'])
        ->where('role', 'student')
        ->findOrFail($userId);

    // Latest active assessment
    $latestAssessment = StudentAssessment::where('user_id', $userId)
        ->where('status', 'active')
        ->latest()
        ->first();

    // All transactions with fee relation
    $transactions = Transaction::where('user_id', $userId)
        ->with('fee')
        ->orderBy('created_at', 'desc')
        ->get();

    // Student payments
    $payments = Payment::where('student_id', $student->student->id ?? null)
        ->orderBy('paid_at', 'desc')
        ->get();

    // Fee breakdown
    $feeBreakdown = $transactions->where('kind', 'charge')
        ->groupBy('type')
        ->map(function ($group) {
            return [
                'category' => $group->first()->type,
                'total' => $group->sum('amount'),
                'items' => $group->count(),
            ];
        });

    // === ✨ FIX: Add all required top-level props for AccountOverview.vue ===

    $account = $student->account ?? null;

    $assessmentLines = $latestAssessment->subjects ?? [];

    $termsOfPayment = $latestAssessment->payment_terms ?? null;

    $fees = $transactions->where('kind', 'charge')->map(function ($t) {
        return [
            'id' => $t->fee_id ?? null,
            'name' => $t->meta['fee_name'] 
                ?? ($t->fee->name ?? ($t->type ?? 'Fee')),
            'amount' => $t->amount,
            'category' => $t->type ?? 'Other',
        ];
    })->values();

    $currentTerm = [
        'year' => $latestAssessment->year ?? now()->year,
        'semester' => $latestAssessment->semester ?? '1st Sem',
    ];

    return Inertia::render('StudentFees/Show', [
        'student' => $student,
        'student_model_id' => $student->student->id ?? null,
        'account' => $account,
        'assessment' => $latestAssessment,
        'assessmentLines' => $assessmentLines,
        'termsOfPayment' => $termsOfPayment,
        'transactions' => $transactions,
        'payments' => $payments,
        'fees' => $fees,
        'currentTerm' => $currentTerm,
        'feeBreakdown' => $feeBreakdown->values(),
    ]);
}

    /**
     * Store payment for student
     */
    public function storePayment(Request $request, $userId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'description' => 'nullable|string|max:255',
            'payment_date' => 'required|date',
        ]);

        $student = User::with('student')->where('role', 'student')->findOrFail($userId);

        // Ensure student has a student record
        if (!$student->student) {
            return back()->withErrors(['error' => 'Student record not found. Please contact administrator.']);
        }

        DB::beginTransaction();
        try {
            $paymentDate = $validated['payment_date'] ?? now();

            // Create payment record
            $payment = Payment::create([
                'student_id' => $student->student->id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                'description' => $validated['description'] ?? 'Payment',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $paymentDate,
            ]);

            // Create transaction record
            Transaction::create([
                'user_id' => $userId,
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

            // Recalculate balance
            \App\Services\AccountService::recalculate($student);

            DB::commit();

            return back()->with('success', 'Payment recorded successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment recording failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again or contact support.'
            ]);
        }
    }

    /**
     * Edit assessment
     */
    public function edit($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $subjects = Subject::active()
            ->where('course', $student->course)
            ->where('year_level', $student->year_level)
            ->get();

        $fees = Fee::active()
            ->whereIn('category', ['Laboratory', 'Library', 'Athletic', 'Miscellaneous'])
            ->get();

        return Inertia::render('StudentFees/Edit', [
            'student' => $student,
            'assessment' => $assessment,
            'subjects' => $subjects,
            'fees' => $fees,
        ]);
    }

    /**
     * Export assessment to PDF
     */
    public function exportPdf($userId)
    {
        $student = User::with(['student', 'account'])
            ->where('role', 'student')
            ->findOrFail($userId);

        $assessment = StudentAssessment::where('user_id', $userId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $transactions = Transaction::where('user_id', $userId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('student_id', $student->student->id ?? null)
            ->orderBy('paid_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.student-assessment', [
            'student' => $student,
            'assessment' => $assessment,
            'transactions' => $transactions,
            'payments' => $payments,
        ]);

        return $pdf->download("assessment-{$student->student_id}.pdf");
    }

    /**
     * Show create student form with OBE program selection
     */
    public function createStudent()
    {
        // Get available programs
        $programs = Program::active()
            ->orderBy('name')
            ->get()
            ->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->full_name,
                ];
            });

        // Get legacy courses for backward compatibility
        $legacyCourses = User::where('role', 'student')
            ->whereNotNull('course')
            ->distinct()
            ->pluck('course')
            ->sort()
            ->values();
        
        if ($legacyCourses->isEmpty()) {
            $legacyCourses = collect([
                'BS Electrical Engineering Technology',
                'BS Electronics Engineering Technology',
                'BS Computer Science',
                'BS Information Technology',
            ]);
        }
        
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        
        return Inertia::render('StudentFees/CreateStudent', [
            'programs' => $programs,
            'legacyCourses' => $legacyCourses,
            'yearLevels' => $yearLevels,
        ]);
    }

    /**
    * Get available terms for a program (AJAX endpoint)
    */
    public function getAvailableTerms(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $terms = $this->curriculumService->getAvailableTerms($request->program_id);

        return response()->json([
            'terms' => $terms,
        ]);
    }

    /**
     * Get curriculum preview (AJAX endpoint)
     */
    public function getCurriculumPreview(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
        ]);

        $curriculum = $this->curriculumService->getCurriculumForTerm(
            $request->program_id,
            $request->year_level,
            $request->semester,
            $request->school_year
        );

        if (!$curriculum) {
            return response()->json([
                'error' => 'No curriculum found for the selected term.',
            ], 404);
        }

        $curriculum->load('courses', 'program');

        return response()->json([
            'curriculum' => [
                'id' => $curriculum->id,
                'program' => $curriculum->program->full_name,
                'term' => $curriculum->term_description,
                'tuition_per_unit' => $curriculum->tuition_per_unit,
                'lab_fee' => $curriculum->lab_fee,
                'registration_fee' => $curriculum->registration_fee,
                'misc_fee' => $curriculum->misc_fee,
                'courses' => $curriculum->courses->map(function ($course) use ($curriculum) {
                    $tuition = $course->total_units * $curriculum->tuition_per_unit;
                    $labFee = $course->has_lab ? $curriculum->lab_fee : 0;
                    return [
                        'code' => $course->code,
                        'title' => $course->title,
                        'lec_units' => $course->lec_units,
                        'lab_units' => $course->lab_units,
                        'total_units' => $course->total_units,
                        'tuition' => $tuition,
                        'lab_fee' => $labFee,
                        'total' => $tuition + $labFee,
                    ];
                }),
                'totals' => [
                    'tuition' => $curriculum->calculateTuition(),
                    'lab_fees' => $curriculum->calculateLabFees(),
                    'registration_fee' => $curriculum->registration_fee,
                    'misc_fee' => $curriculum->misc_fee,
                    'total_assessment' => $curriculum->calculateTotalAssessment(),
                ],
                'payment_terms' => $curriculum->generatePaymentTerms(),
            ],
        ]);
    }

    /**
     * Store new student with OBE curriculum support
     */
    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email',
            'birthday' => 'required|date',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'year_level' => 'required|string',
            'student_id' => 'nullable|string|unique:users,student_id',
            
            // OBE fields
            'program_id' => 'nullable|exists:programs,id',
            'semester' => 'nullable|string',
            'school_year' => 'nullable|string',
            
            // Legacy field
            'course' => 'nullable|string',
            
            // Auto-generate assessment flag
            'auto_generate_assessment' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            // Auto-generate ID if empty
            $studentId = $validated['student_id'] ?: $this->generateUniqueStudentId();

            // Determine course name
            if ($validated['program_id']) {
                $program = Program::find($validated['program_id']);
                $courseName = $program->full_name;
            } else {
                $courseName = $validated['course'];
            }

            // Create user record
            $user = User::create([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'year_level' => $validated['year_level'],
                'course' => $courseName,
                'student_id' => $studentId,
                'role' => 'student',
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make('password'),
            ]);

            // Create Student model entry
            \App\Models\Student::create([
                'user_id' => $user->id,
                'student_id' => $studentId,
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'course' => $courseName,
                'year_level' => $validated['year_level'],
                'status' => 'enrolled',
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'total_balance' => 0,
            ]);

            // Create account
            $user->account()->create(['balance' => 0]);

            // Auto-generate OBE assessment if requested
            if ($request->boolean('auto_generate_assessment') && $validated['program_id']) {
                $curriculum = $this->curriculumService->getCurriculumForTerm(
                    $validated['program_id'],
                    $validated['year_level'],
                    $validated['semester'] ?? '1st Sem',
                    $validated['school_year'] ?? '2025-2026'
                );

                if ($curriculum) {
                    $this->curriculumService->generateAssessment($user, $curriculum);
                }
            }

            DB::commit();

            return redirect()
                ->route('student-fees.show', $user->id)
                ->with('success', 'Student created successfully with OBE curriculum!');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Student creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to create student: ' . $e->getMessage(),
            ])->withInput();
        }
    }

    private function generateUniqueStudentId(): string
    {
        $year = now()->year;
        
        // Use database transaction to prevent race conditions
        return DB::transaction(function () use ($year) {
            // Lock the table to prevent concurrent ID generation
            $lastStudent = User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                // Extract the number part and increment
                $lastNumber = intval(substr($lastStudent->student_id, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newStudentId = "{$year}-{$newNumber}";
            
            // Double-check uniqueness
            $attempts = 0;
            while (User::where('student_id', $newStudentId)->exists() && $attempts < 10) {
                $lastNumber = intval($newNumber);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newStudentId = "{$year}-{$newNumber}";
                $attempts++;
            }
            
            if ($attempts >= 10) {
                throw new \Exception('Unable to generate unique student ID after multiple attempts.');
            }
            
            return $newStudentId;
        });
    }
}
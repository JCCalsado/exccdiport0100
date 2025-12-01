<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\CurriculumService;
use App\Services\AssessmentGeneratorService;
use App\Models\Program;
use App\Models\Curriculum;
use App\Services\AssessmentDataService;
use App\Services\StudentCreationService;
use App\Http\Requests\StoreStudentRequest;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class StudentFeeController extends Controller
{
    protected $curriculumService;
    protected $studentCreationService;
    protected $assessmentGenerator;

    public function __construct(
        CurriculumService $curriculumService,
        StudentCreationService $studentCreationService,
        AssessmentGeneratorService $assessmentGenerator
    ) {
        $this->curriculumService = $curriculumService;
        $this->studentCreationService = $studentCreationService;
        $this->assessmentGenerator = $assessmentGenerator;
    }

    /**
     * Display listing of students for fee management
     * Now uses Student model and returns account_id in results.
     */
    public function index(Request $request)
    {
        $query = Student::with(['user', 'account'])
            ->whereNotNull('user_id'); // ensure linked user

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->orderBy('last_name')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id,
                    'student_id' => $student->student_id,
                    'name' => $student->last_name . ', ' . $student->first_name . ($student->middle_initial ? ' ' . $student->middle_initial : ''),
                    'email' => $student->email ?? ($student->user->email ?? null),
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'status' => $student->status,
                ];
            });

        $courses = Student::whereNotNull('course')
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

    /**
     * Show create assessment form (AJAX get_data or full form)
     * When providing get_data expect account_id param.
     */
    public function create(Request $request)
    {
        if ($request->has('get_data') && $request->has('account_id')) {
            $request->validate([
                'account_id' => 'required|exists:students,account_id',
            ]);

            $student = Student::where('account_id', $request->account_id)->firstOrFail();

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
                'student' => [
                    'account_id' => $student->account_id,
                    'name' => $student->last_name . ', ' . $student->first_name,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                ],
            ]);
        }

        $students = Student::whereHas('user')
            ->where('status', 'enrolled')
            ->orderBy('last_name')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id,
                    'student_id' => $student->student_id,
                    'name' => $student->last_name . ', ' . $student->first_name,
                    'email' => $student->email ?? ($student->user->email ?? null),
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'status' => $student->status,
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
     * Store new assessment (now tied to account_id)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:students,account_id',
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
            $student = Student::where('account_id', $validated['account_id'])->with('user', 'account')->firstOrFail();

            // compute tuition fee & other fees from validated payload
            $tuitionFee = collect($validated['subjects'])->sum('amount');
            $otherFeesTotal = isset($validated['other_fees'])
                ? collect($validated['other_fees'])->sum('amount')
                : 0;

            $assessment = StudentAssessment::create([
                // primary linkage is account_id
                'account_id' => $student->account_id,
                // keep user_id for compatibility when user exists
                'user_id' => $student->user_id ?? null,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'subjects' => $validated['subjects'],
                'fee_breakdown' => $validated['other_fees'] ?? [],
                'status' => 'active',
                'school_year' => $validated['school_year'],
                'semester' => $validated['semester'],
            ]);

            $this->generatePaymentTermsFromAssessment($assessment, $student);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $student->account_id)
                ->with('success', 'Student fee assessment created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment creation failed', [
                'account_id' => $request->input('account_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Show student assessment and payment terms by account_id
     */
    public function show($accountId)
    {
        $student = Student::with(['user', 'account', 'paymentTerms'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $data = AssessmentDataService::getUnifiedAssessmentData($accountId);

        return Inertia::render('StudentFees/Show', $data);
    }

    /**
     * Store payment for a student identified by account_id
     */
    public function storePayment(Request $request, $accountId)
    {
        $student = Student::with(['user', 'account'])->where('account_id', $accountId)->firstOrFail();
        $balance = abs($student->account->balance ?? 0);

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                // max: {$balance} can cause issue if balance is 0; skip dynamic max and check manually
            ],
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'description' => 'nullable|string|max:255',
            'payment_date' => 'required|date|before_or_equal:today',
        ]);

        if ($validated['amount'] > $balance) {
            return back()->withErrors(['amount' => 'Payment amount cannot exceed outstanding balance.']);
        }

        DB::beginTransaction();
        try {
            $paymentDate = $validated['payment_date'] ?? now();

            // Create Payment record - link with account_id and student_id for compatibility
            $payment = Payment::create([
                'account_id' => $student->account_id,
                'student_id' => $student->id,
                'user_id' => $student->user_id ?? null,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                'description' => $validated['description'] ?? 'Payment',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $paymentDate,
            ]);

            // Create Transaction using account_id
            Transaction::create([
                'account_id' => $student->account_id,
                'user_id' => $student->user_id ?? null, // keep for compatibility
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

            // Recalculate account balance - pass user if service expects user
            \App\Services\AccountService::recalculate($student->user ?? $student);

            DB::commit();

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
     * Edit assessment for given account_id
     */
    public function edit($accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $assessment = StudentAssessment::where('account_id', $accountId)
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
            'student' => [
                'account_id' => $student->account_id,
                'name' => $student->last_name . ', ' . $student->first_name,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
            'assessment' => $assessment,
            'subjects' => $subjects,
            'fees' => $fees,
        ]);
    }

    /**
     * Update assessment (tied to account_id)
     */
    public function update(Request $request, $accountId)
    {
        $validated = $request->validate([
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

        $student = Student::where('account_id', $accountId)->with('user', 'account')->firstOrFail();

        DB::beginTransaction();
        try {
            $assessment = StudentAssessment::where('account_id', $accountId)
                ->where('status', 'active')
                ->latest()
                ->firstOrFail();

            $tuitionFee = collect($validated['subjects'])->sum('amount');
            $otherFeesTotal = isset($validated['other_fees'])
                ? collect($validated['other_fees'])->sum('amount')
                : 0;

            $assessment->update([
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'subjects' => $validated['subjects'],
                'fee_breakdown' => $validated['other_fees'] ?? [],
            ]);

            // remove previous transactions related to this assessment (by meta assessment_id) for this account
            Transaction::where('account_id', $accountId)
                ->where('meta->assessment_id', $assessment->id)
                ->delete();

            foreach ($validated['subjects'] as $subject) {
                Transaction::create([
                    'account_id' => $accountId,
                    'user_id' => $student->user_id ?? null,
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

            if (isset($validated['other_fees'])) {
                foreach ($validated['other_fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'account_id' => $accountId,
                        'user_id' => $student->user_id ?? null,
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

            \App\Services\AccountService::recalculate($student->user ?? $student);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $accountId)
                ->with('success', 'Student assessment updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment update failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to update assessment: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Export assessment PDF for a student by account_id
     */
    public function exportPdf($accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $assessment = StudentAssessment::where('account_id', $accountId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $transactions = Transaction::where('account_id', $accountId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('account_id', $accountId)
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
     * Create student (unchanged, but ensure Student record includes account_id at creation)
     */
    public function createStudent()
    {
        $programs = Program::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($program) {
                return [
                    'id' => $program->id,
                    'code' => $program->code,
                    'name' => $program->name,
                    'full_name' => $program->full_name,
                    'major' => $program->major,
                ];
            });

        $legacyCourses = collect([
            'BS Electrical Engineering Technology',
            'BS Electronics Engineering Technology',
            'BS Computer Science',
            'BS Information Technology',
            'BS Accountancy',
        ]);

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];

        $currentYear = now()->year;
        $schoolYears = [];
        for ($i = 0; $i < 3; $i++) {
            $year = $currentYear + $i;
            $schoolYears[] = "{$year}-" . ($year + 1);
        }

        return Inertia::render('StudentFees/CreateStudent', [
            'programs' => $programs,
            'legacyCourses' => $legacyCourses,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Get available terms from curriculum service
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
     * Curriculum preview
     */
    public function getCurriculumPreview(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
        ]);

        try {
            $preview = $this->assessmentGenerator->getCurriculumPreview(
                $validated['program_id'],
                $validated['year_level'],
                $validated['semester'],
                $validated['school_year']
            );

            if (!$preview) {
                return response()->json([
                    'error' => 'No curriculum found for the selected term.',
                    'message' => 'Please select a different term or create the student without auto-assessment.',
                ], 404);
            }

            return response()->json([
                'curriculum' => $preview,
            ]);

        } catch (\Exception $e) {
            Log::error('Curriculum preview failed', [
                'request' => $validated,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to load curriculum preview.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * StoreStudent - updated to ensure Student model receives account_id at creation
     */
    public function storeStudent(Request $request)
    {
        // ENHANCED VALIDATION
        $validated = $request->validate([
            // Personal Information
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email',
            'birthday' => 'required|date|before:today',

            // Contact Information
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',

            // Academic Information
            'year_level' => 'required|string|in:1st Year,2nd Year,3rd Year,4th Year',
            'student_id' => 'nullable|string|unique:users,student_id',

            // Conditional validation
            'program_id' => 'nullable|exists:programs,id',
            'semester' => 'nullable|string|in:1st Sem,2nd Sem,Summer',
            'school_year' => 'nullable|string|regex:/^\d{4}-\d{4}$/',
            'course' => 'nullable|string|max:255',

            // Options
            'auto_generate_assessment' => 'boolean',
        ]);

        if (!$validated['program_id'] && !$validated['course']) {
            return back()->withErrors([
                'error' => 'Either an OBE program or legacy course must be selected.',
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            // Generate student ID if not provided
            $studentId = $validated['student_id'] ?? $this->generateUniqueStudentId();

            // Determine course name and mode
            if ($validated['program_id']) {
                $program = Program::findOrFail($validated['program_id']);
                $courseName = $program->full_name;
                $isOBE = true;
            } else {
                $courseName = $validated['course'];
                $isOBE = false;
            }

            // Create User
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

            // Create Student Profile (ensure account_id is set elsewhere or use generation)
            $student = Student::create([
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
                // note: account_id should be generated either via model event or separately.
            ]);

            // Create Account
            $user->account()->create(['balance' => 0]);

            // If the Student model is expected to have account_id populated, sync it now if available:
            if ($user->account) {
                $student->account_id = $user->account->id ?? $student->account_id;
                $student->save();
            }

            // Auto-generate assessment for OBE students
            if ($isOBE && $request->boolean('auto_generate_assessment')) {
                $curriculum = Curriculum::where('program_id', $validated['program_id'])
                    ->where('year_level', $validated['year_level'])
                    ->where('semester', $validated['semester'])
                    ->where('school_year', $validated['school_year'])
                    ->where('is_active', true)
                    ->first();

                if ($curriculum) {
                    $assessment = $this->assessmentGenerator->generateFromCurriculum($user, $curriculum);

                    Log::info('Assessment generated successfully', [
                        'user_id' => $user->id,
                        'assessment_id' => $assessment->id,
                        'total' => $assessment->total_assessment,
                    ]);

                    $successMessage = 'Student created successfully with assessment!';
                } else {
                    Log::warning('No curriculum found for auto-assessment', [
                        'program_id' => $validated['program_id'],
                        'year_level' => $validated['year_level'],
                        'semester' => $validated['semester'],
                        'school_year' => $validated['school_year'],
                    ]);

                    $successMessage = 'Student created successfully, but no curriculum found. Please create assessment manually.';
                }
            } else {
                $successMessage = 'Student created successfully!';
            }

            DB::commit();

            return redirect()
                ->route('student-fees.show', $student->account_id ?? ($user->account->id ?? $user->id))
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Student creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except('password'),
            ]);

            return back()->withErrors([
                'error' => 'Failed to create student: ' . $e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Helper to generate a unique student id
     */
    protected function generateUniqueStudentId(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            $lastStudent = User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            $lastNumber = $lastStudent
                ? intval(substr($lastStudent->student_id, 5))
                : 0;

            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

            return "{$year}-{$newNumber}";
        });
    }

    /**
     * Create payment terms from assessment - now accepts $student (Student model) to attach account_id
     */
    protected function generatePaymentTermsFromAssessment(StudentAssessment $assessment, Student $student): void
    {
        $totalAmount = $assessment->total_assessment;
        $termAmount = round($totalAmount / 5, 2);
        $lastTermAmount = $totalAmount - ($termAmount * 4);

        $terms = [
            ['name' => 'Upon Registration', 'order' => 1, 'weeks' => 0, 'amount' => $termAmount],
            ['name' => 'Prelim', 'order' => 2, 'weeks' => 6, 'amount' => $termAmount],
            ['name' => 'Midterm', 'order' => 3, 'weeks' => 12, 'amount' => $termAmount],
            ['name' => 'Semi-Final', 'order' => 4, 'weeks' => 15, 'amount' => $termAmount],
            ['name' => 'Final', 'order' => 5, 'weeks' => 18, 'amount' => $lastTermAmount],
        ];

        // determine a sensible start date (assume Aug 1st for school year)
        try {
            $startDate = Carbon::parse(explode('-', $assessment->school_year)[0] . '-08-01');
        } catch (\Exception $e) {
            $startDate = Carbon::now();
        }

        foreach ($terms as $term) {
            StudentPaymentTerm::create([
                'account_id' => $student->account_id,
                'curriculum_id' => $assessment->curriculum_id ?? null,
                'school_year' => $assessment->school_year ?? null,
                'semester' => $assessment->semester ?? null,
                'term_name' => $term['name'],
                'term_order' => $term['order'],
                'amount' => $term['amount'],
                'due_date' => $startDate->copy()->addWeeks($term['weeks']),
                'status' => 'pending',
                'paid_amount' => 0,
            ]);
        }
    }
}
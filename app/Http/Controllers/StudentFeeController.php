<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\Subject;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use App\Models\Program;
use App\Models\Curriculum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\CurriculumService;
use App\Services\AssessmentGeneratorService;
use App\Services\AssessmentDataService;
use App\Services\StudentCreationService;
use Carbon\Carbon;
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
     * ✅ FIXED: Display listing using account_id
     */
    public function index(Request $request)
    {
        $query = Student::with(['user', 'account'])
            ->whereNotNull('account_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_id', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
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
                    'account_id' => $student->account_id, // ✅ PRIMARY
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'status' => $student->status,
                ];
            });

        $courses = Student::whereNotNull('course')
            ->distinct()
            ->pluck('course');

        return Inertia::render('StudentFees/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status']),
            'courses' => $courses,
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'statuses' => [
                'enrolled' => 'Enrolled',
                'graduated' => 'Graduated',
                'inactive' => 'Inactive',
            ],
        ]);
    }

    /**
     * ✅ FIXED: Create form with account_id support
     */
    public function create(Request $request)
    {
        // AJAX endpoint for student data
        if ($request->has('get_data') && $request->has('account_id')) {
            return $this->getStudentDataForAssessment($request->account_id);
        }

        $students = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
            ->orderBy('last_name')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id, // ✅ PRIMARY
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
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
     * ✅ FIXED: Get student data by account_id
     */
    protected function getStudentDataForAssessment(string $accountId)
    {
        $student = Student::where('account_id', $accountId)->firstOrFail();

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
                    'price_per_unit' => (float) $subject->price_per_unit,
                    'has_lab' => $subject->has_lab,
                    'lab_fee' => (float) $subject->lab_fee,
                    'total_cost' => (float) $subject->total_cost,
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
                    'amount' => (float) $fee->amount,
                ];
            });

        return response()->json([
            'subjects' => $subjects,
            'fees' => $fees,
            'student' => [
                'account_id' => $student->account_id,
                'name' => $student->full_name,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
        ]);
    }

    /**
     * ✅ FIXED: Store assessment using account_id
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
            $student = Student::where('account_id', $validated['account_id'])
                ->with('user', 'account')
                ->firstOrFail();

            $tuitionFee = collect($validated['subjects'])->sum('amount');
            $otherFeesTotal = isset($validated['other_fees'])
                ? collect($validated['other_fees'])->sum('amount')
                : 0;

            $assessment = StudentAssessment::create([
                'account_id' => $student->account_id, // ✅ PRIMARY
                'user_id' => $student->user_id, // Compatibility
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'subjects' => $validated['subjects'],
                'fee_breakdown' => $validated['other_fees'] ?? [],
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            // Create transactions using account_id
            $this->createTransactionsFromAssessment($assessment, $student);

            // Create payment terms using account_id
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
     * ✅ FIXED: Show assessment by account_id
     */
    public function show($accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $data = AssessmentDataService::getUnifiedAssessmentData($accountId);

        return Inertia::render('StudentFees/Show', array_merge($data, [
            'account_id' => $accountId,
        ]));
    }

    /**
     * ✅ FIXED: Store payment using account_id
     */
    public function storePayment(Request $request, $accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $balance = abs($student->account->balance ?? 0);

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
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

            // Create Payment record
            $payment = Payment::create([
                'account_id' => $student->account_id, // ✅ PRIMARY
                'student_id' => $student->id, // Compatibility
                'user_id' => $student->user_id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                'description' => $validated['description'] ?? 'Payment',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $paymentDate,
            ]);

            // Create Transaction
            Transaction::create([
                'account_id' => $student->account_id, // ✅ PRIMARY
                'user_id' => $student->user_id, // Compatibility
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
            \App\Services\AccountService::recalculate($student->user);

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
     * ✅ FIXED: Edit assessment
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
                'name' => $student->full_name,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
            'assessment' => $assessment,
            'subjects' => $subjects,
            'fees' => $fees,
        ]);
    }

    /**
     * ✅ FIXED: Update assessment
     */
    public function update(Request $request, string $accountId)
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

        $student = Student::where('account_id', $accountId)
            ->with('user', 'account')
            ->firstOrFail();

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

            // Create new transactions
            foreach ($validated['subjects'] as $subject) {
                Transaction::create([
                    'account_id' => $accountId, // ✅ PRIMARY
                    'user_id' => $student->user_id,
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
                    ],
                ]);
            }

            if (isset($validated['other_fees'])) {
                foreach ($validated['other_fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'account_id' => $accountId, // ✅ PRIMARY
                        'user_id' => $student->user_id,
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

            \App\Services\AccountService::recalculate($student->user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $accountId)
                ->with('success', 'Student assessment updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment update failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update assessment: ' . $e->getMessage()]);
        }
    }

    /**
     * ✅ FIXED: Export PDF
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
     * ✅ FIXED: Create student form
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
     * ✅ FIXED: Store new student with account_id auto-generation
     */
    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email',
            'birthday' => 'required|date|before:today',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'year_level' => 'required|string|in:1st Year,2nd Year,3rd Year,4th Year',
            'student_id' => 'nullable|string|unique:users,student_id',
            'program_id' => 'nullable|exists:programs,id',
            'semester' => 'nullable|string|in:1st Sem,2nd Sem,Summer',
            'school_year' => 'nullable|string|regex:/^\d{4}-\d{4}$/',
            'course' => 'nullable|string|max:255',
            'auto_generate_assessment' => 'boolean',
        ]);

        if (!$validated['program_id'] && !$validated['course']) {
            return back()->withErrors([
                'error' => 'Either an OBE program or legacy course must be selected.',
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            $studentId = $validated['student_id'] ?? $this->generateUniqueStudentId();

            // Determine course name
            if ($validated['program_id']) {
                $program = Program::findOrFail($validated['program_id']);
                $courseName = $program->full_name;
                $isOBE = true;
            } else {
                $courseName = $validated['course'];
                $isOBE = false;
            }

            // Create User
            $user = \App\Models\User::create([
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
                'status' => \App\Models\User::STATUS_ACTIVE,
                'password' => Hash::make('password'),
            ]);

            // Create Student Profile (account_id auto-generates)
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
            ]);

            // Create Account
            $user->account()->create(['balance' => 0]);

            // Auto-generate assessment for OBE students
            if ($isOBE && $request->boolean('auto_generate_assessment')) {
                $curriculum = Curriculum::where('program_id', $validated['program_id'])
                    ->where('year_level', $validated['year_level'])
                    ->where('semester', $validated['semester'])
                    ->where('school_year', $validated['school_year'])
                    ->where('is_active', true)
                    ->first();

                if ($curriculum) {
                    $this->assessmentGenerator->generateFromCurriculum($user, $curriculum);
                    $successMessage = 'Student created successfully with assessment!';
                } else {
                    $successMessage = 'Student created successfully, but no curriculum found.';
                }
            } else {
                $successMessage = 'Student created successfully!';
            }

            DB::commit();

            return redirect()
                ->route('student-fees.show', $student->account_id)
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create student: ' . $e->getMessage()]);
        }
    }

    /**
     * ✅ Helper: Generate unique student ID
     */
    protected function generateUniqueStudentId(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $lastStudent = \App\Models\User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            $lastNumber = $lastStudent ? intval(substr($lastStudent->student_id, 5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            return "{$year}-{$newNumber}";
        });
    }

    /**
     * ✅ Helper: Create transactions from assessment
     */
    protected function createTransactionsFromAssessment(StudentAssessment $assessment, Student $student): void
    {
        foreach ($assessment->subjects as $subject) {
            Transaction::create([
                'account_id' => $student->account_id, // ✅ PRIMARY
                'user_id' => $student->user_id,
                'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                'kind' => 'charge',
                'type' => 'Tuition',
                'year' => explode('-', $assessment->school_year)[0],
                'semester' => $assessment->semester,
                'amount' => $subject['amount'],
                'status' => 'pending',
                'meta' => [
                    'assessment_id' => $assessment->id,
                    'subject_id' => $subject['id'],
                ],
            ]);
        }

        foreach ($assessment->fee_breakdown ?? [] as $fee) {
            $feeModel = Fee::find($fee['id']);
            Transaction::create([
                'account_id' => $student->account_id, // ✅ PRIMARY
                'user_id' => $student->user_id,
                'fee_id' => $fee['id'],
                'reference' => 'FEE-' . strtoupper(Str::random(8)),
                'kind' => 'charge',
                'type' => $feeModel->category,
                'year' => explode('-', $assessment->school_year)[0],
                'semester' => $assessment->semester,
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

    /**
     * ✅ Helper: Generate payment terms
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

        try {
            $startDate = Carbon::parse(explode('-', $assessment->school_year)[0] . '-08-01');
        } catch (\Exception $e) {
            $startDate = Carbon::now();
        }

        foreach ($terms as $term) {
            StudentPaymentTerm::create([
                'account_id' => $student->account_id, // ✅ PRIMARY
                'user_id' => $student->user_id,
                'curriculum_id' => $assessment->curriculum_id,
                'school_year' => $assessment->school_year,
                'semester' => $assessment->semester,
                'term_name' => $term['name'],
                'term_order' => $term['order'],
                'amount' => $term['amount'],
                'due_date' => $startDate->copy()->addWeeks($term['weeks']),
                'status' => 'pending',
                'paid_amount' => 0,
            ]);
        }
    }

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
                ], 404);
            }

            return response()->json(['curriculum' => $preview]);

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

    public function getAvailableTerms(Request $request)
    {
        $request->validate([
            'program_id' => 'required|exists:programs,id',
        ]);

        $terms = $this->curriculumService->getAvailableTerms($request->program_id);

        return response()->json(['terms' => $terms]);
    }
}

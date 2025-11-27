<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\Program;
use App\Models\User;
use App\Models\StudentAssessment;
use App\Models\StudentCurriculum;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CurriculumService
{
    /**
     * Get curriculum for a specific program and term
     */
    public function getCurriculumForTerm(int $programId, string $yearLevel, string $semester, string $schoolYear): ?Curriculum
    {
        return Curriculum::with(['program', 'courses'])
            ->forTerm($programId, $yearLevel, $semester, $schoolYear)
            ->active()
            ->first();
    }

    /**
     * Generate assessment with PAYMENT TERMS (not immediate charges)
     */
    public function generateAssessment(User $student, Curriculum $curriculum): StudentAssessment
    {
        if ($curriculum->courses->isEmpty()) {
            throw new \Exception('Cannot generate assessment: Curriculum has no courses');
        }
        
        DB::beginTransaction();
        try {
            // Calculate fees
            $tuitionFee = $curriculum->calculateTuition();
            $labFees = $curriculum->calculateLabFees();
            $registrationFee = $curriculum->registration_fee;
            $miscFee = $curriculum->misc_fee;
            $otherFees = $labFees + $miscFee;
            $totalAssessment = $tuitionFee + $otherFees + $registrationFee;

            // Prepare subjects data
            $subjects = $curriculum->courses->map(function ($course) use ($curriculum) {
                $courseFee = $course->total_units * $curriculum->tuition_per_unit;
                $labFee = $course->has_lab ? $curriculum->lab_fee : 0;
                
                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'lec_units' => $course->lec_units,
                    'lab_units' => $course->lab_units,
                    'total_units' => $course->total_units,
                    'has_lab' => $course->has_lab,
                    'tuition' => $courseFee,
                    'lab_fee' => $labFee,
                    'total' => $courseFee + $labFee,
                ];
            })->toArray();

            // Prepare fee breakdown
            $feeBreakdown = [
                ['name' => 'Registration Fee', 'amount' => $registrationFee],
                ['name' => 'Laboratory Fee', 'amount' => $labFees],
                ['name' => 'Miscellaneous Fee', 'amount' => $miscFee],
            ];

            // Generate payment terms
            $paymentTerms = $curriculum->generatePaymentTerms();

            // Create assessment
            $assessment = StudentAssessment::create([
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $curriculum->year_level,
                'semester' => $curriculum->semester,
                'school_year' => $curriculum->school_year,
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFees,
                'registration_fee' => $registrationFee,
                'total_assessment' => $totalAssessment,
                'subjects' => $subjects,
                'fee_breakdown' => $feeBreakdown,
                'payment_terms' => $paymentTerms,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            // Enroll student in curriculum
            StudentCurriculum::create([
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'enrollment_status' => 'active',
                'enrolled_at' => now(),
            ]);

            // ✅ CREATE PAYMENT TERMS (NOT TRANSACTIONS)
            $this->generatePaymentTermsForStudent($student, $curriculum, $paymentTerms);

            // ✅ NO TRANSACTION CREATION HERE
            // Transactions are only created when actual payments are recorded

            // Account balance should remain 0 until terms are converted to charges
            // This happens when payment deadlines pass or admin manually posts charges
            
            DB::commit();
            return $assessment;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate payment terms for a student based on curriculum
     */
    protected function generatePaymentTermsForStudent(User $student, Curriculum $curriculum, array $paymentTerms): void
    {
        $termNames = [
            'upon_registration' => 'Upon Registration',
            'prelim' => 'Prelim',
            'midterm' => 'Midterm',
            'semi_final' => 'Semi-Final',
            'final' => 'Final',
        ];

        $order = 1;
        $startDate = Carbon::parse($curriculum->school_year . '-08-01'); // August 1st

        foreach ($termNames as $key => $name) {
            if (isset($paymentTerms[$key]) && $paymentTerms[$key] > 0) {
                StudentPaymentTerm::create([
                    'user_id' => $student->id,
                    'curriculum_id' => $curriculum->id,
                    'school_year' => $curriculum->school_year,
                    'semester' => $curriculum->semester,
                    'term_name' => $name,
                    'term_order' => $order,
                    'amount' => $paymentTerms[$key],
                    'due_date' => $this->calculateDueDate($startDate, $order),
                    'status' => 'pending',
                    'paid_amount' => 0,
                ]);
                $order++;
            }
        }
    }

    /**
     * Calculate due dates for payment terms
     */
    protected function calculateDueDate(Carbon $startDate, int $order): Carbon
    {
        // Registration: Start of semester
        // Prelim: +6 weeks
        // Midterm: +12 weeks
        // Semi-Final: +15 weeks
        // Final: +18 weeks
        
        $weeksMap = [
            1 => 0,  // Registration
            2 => 6,  // Prelim
            3 => 12, // Midterm
            4 => 15, // Semi-Final
            5 => 18, // Final
        ];

        return $startDate->copy()->addWeeks($weeksMap[$order] ?? 0);
    }

    /**
     * Get available programs
     */
    public function getAvailablePrograms()
    {
        return Program::active()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get available terms for a program
     */
    public function getAvailableTerms(int $programId)
    {
        return Curriculum::where('program_id', $programId)
            ->active()
            ->select('year_level', 'semester', 'school_year')
            ->distinct()
            ->orderBy('school_year', 'desc')
            ->orderByRaw("FIELD(year_level, '1st Year', '2nd Year', '3rd Year', '4th Year')")
            ->orderByRaw("FIELD(semester, '1st Sem', '2nd Sem', 'Summer')")
            ->get()
            ->groupBy('year_level');
    }
}
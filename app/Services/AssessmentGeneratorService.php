<?php

namespace App\Services;

use App\Models\User;
use App\Models\Curriculum;
use App\Models\StudentAssessment;
use App\Models\StudentCurriculum;
use App\Models\StudentPaymentTerm;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AssessmentGeneratorService
{
    /**
     * Generate complete assessment for a student from curriculum
     */
    public function generateFromCurriculum(User $student, Curriculum $curriculum): StudentAssessment
    {
        if ($curriculum->courses->isEmpty()) {
            throw new \Exception('Cannot generate assessment: Curriculum has no courses');
        }

        DB::beginTransaction();
        try {
            // Calculate all fees
            $tuitionFee = $curriculum->calculateTuition();
            $labFees = $curriculum->calculateLabFees();
            $registrationFee = $curriculum->registration_fee;
            $miscFee = $curriculum->misc_fee;
            $otherFees = $labFees + $miscFee;
            $totalAssessment = $tuitionFee + $otherFees + $registrationFee;

            // Prepare subjects data with detailed breakdown
            $subjects = $curriculum->courses->map(function ($course) use ($curriculum) {
                $courseTuition = $course->total_units * $curriculum->tuition_per_unit;
                $courseLab = $course->has_lab ? $curriculum->lab_fee : 0;
                
                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'lec_units' => (float) $course->lec_units,
                    'lab_units' => (float) $course->lab_units,
                    'total_units' => (float) $course->total_units,
                    'has_lab' => (bool) $course->has_lab,
                    'tuition' => (float) $courseTuition,
                    'lab_fee' => (float) $courseLab,
                    'misc_fee' => 0.0,
                    'total' => (float) ($courseTuition + $courseLab),
                ];
            })->toArray();

            // Prepare fee breakdown
            $feeBreakdown = [
                [
                    'name' => 'Registration Fee',
                    'category' => 'Registration',
                    'amount' => (float) $registrationFee,
                ],
                [
                    'name' => 'Laboratory Fee',
                    'category' => 'Laboratory',
                    'amount' => (float) $labFees,
                ],
                [
                    'name' => 'Miscellaneous Fee',
                    'category' => 'Miscellaneous',
                    'amount' => (float) $miscFee,
                ],
            ];

            // Generate payment terms
            $paymentTerms = $this->generatePaymentTerms($totalAssessment, $curriculum->term_count);

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
                'created_by' => auth()->id() ?? 1,
            ]);

            // Enroll student in curriculum
            StudentCurriculum::create([
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'enrollment_status' => 'active',
                'enrolled_at' => now(),
            ]);

            // Create payment terms
            $this->createPaymentTerms($student, $curriculum, $paymentTerms);

            // DO NOT create transactions yet - only payment terms
            // Transactions will be created when terms are posted as charges

            DB::commit();
            
            \Log::info('Assessment generated successfully', [
                'user_id' => $student->id,
                'assessment_id' => $assessment->id,
                'total_assessment' => $totalAssessment,
            ]);

            return $assessment;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Assessment generation failed', [
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate payment terms breakdown
     */
    protected function generatePaymentTerms(float $totalAmount, int $termCount = 5): array
    {
        $termAmount = round($totalAmount / $termCount, 2);
        $lastTermAmount = $totalAmount - ($termAmount * ($termCount - 1));

        return [
            'upon_registration' => $termAmount,
            'prelim' => $termAmount,
            'midterm' => $termAmount,
            'semi_final' => $termAmount,
            'final' => $lastTermAmount,
        ];
    }

    /**
     * Create StudentPaymentTerm records
     */
    protected function createPaymentTerms(User $student, Curriculum $curriculum, array $paymentTerms): void
    {
        $termNames = [
            'upon_registration' => 'Upon Registration',
            'prelim' => 'Prelim',
            'midterm' => 'Midterm',
            'semi_final' => 'Semi-Final',
            'final' => 'Final',
        ];

        $order = 1;
        $startDate = Carbon::parse($curriculum->school_year . '-08-01');

        $weeksMap = [
            'upon_registration' => 0,
            'prelim' => 6,
            'midterm' => 12,
            'semi_final' => 15,
            'final' => 18,
        ];

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
                    'due_date' => $startDate->copy()->addWeeks($weeksMap[$key]),
                    'status' => 'pending',
                    'paid_amount' => 0,
                ]);
                $order++;
            }
        }
    }

    /**
     * Get curriculum preview data
     */
    public function getCurriculumPreview(int $programId, string $yearLevel, string $semester, string $schoolYear): ?array
    {
        $curriculum = Curriculum::with(['program', 'courses'])
            ->where('program_id', $programId)
            ->where('year_level', $yearLevel)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->where('is_active', true)
            ->first();

        if (!$curriculum) {
            return null;
        }

        return [
            'id' => $curriculum->id,
            'program' => $curriculum->program->full_name,
            'term' => $curriculum->term_description,
            'courses' => $curriculum->courses->map(function ($course) use ($curriculum) {
                return [
                    'code' => $course->code,
                    'title' => $course->title,
                    'lec_units' => (float) $course->lec_units,
                    'lab_units' => (float) $course->lab_units,
                    'total_units' => (float) $course->total_units,
                    'has_lab' => (bool) $course->has_lab,
                ];
            })->values()->toArray(),
            'totals' => [
                'tuition' => (float) $curriculum->calculateTuition(),
                'lab_fees' => (float) $curriculum->calculateLabFees(),
                'registration_fee' => (float) $curriculum->registration_fee,
                'misc_fee' => (float) $curriculum->misc_fee,
                'total_assessment' => (float) $curriculum->calculateTotalAssessment(),
            ],
        ];
    }
}
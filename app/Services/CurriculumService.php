<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\Program;
use App\Models\User;
use App\Models\StudentAssessment;
use App\Models\StudentCurriculum;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     * Generate assessment for a student based on curriculum
     */
    public function generateAssessment(User $student, Curriculum $curriculum): StudentAssessment
    {
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

            // Create transaction for registration fee
            Transaction::create([
                'user_id' => $student->id,
                'reference' => 'REG-' . strtoupper(Str::random(8)),
                'kind' => 'charge',
                'type' => 'Registration',
                'year' => explode('-', $curriculum->school_year)[0],
                'semester' => $curriculum->semester,
                'amount' => $registrationFee,
                'status' => 'pending',
                'meta' => [
                    'assessment_id' => $assessment->id,
                    'description' => 'Registration Fee',
                    'term' => 'Upon Registration',
                ],
            ]);

            // Create transactions for each subject
            foreach ($subjects as $subject) {
                Transaction::create([
                    'user_id' => $student->id,
                    'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                    'kind' => 'charge',
                    'type' => 'Tuition',
                    'year' => explode('-', $curriculum->school_year)[0],
                    'semester' => $curriculum->semester,
                    'amount' => $subject['total'],
                    'status' => 'pending',
                    'meta' => [
                        'assessment_id' => $assessment->id,
                        'course_code' => $subject['code'],
                        'course_title' => $subject['title'],
                        'units' => $subject['total_units'],
                        'has_lab' => $subject['has_lab'],
                    ],
                ]);
            }

            // Create transactions for other fees
            foreach ($feeBreakdown as $fee) {
                if ($fee['amount'] > 0 && $fee['name'] !== 'Registration Fee') {
                    Transaction::create([
                        'user_id' => $student->id,
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'type' => $fee['name'],
                        'year' => explode('-', $curriculum->school_year)[0],
                        'semester' => $curriculum->semester,
                        'amount' => $fee['amount'],
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'description' => $fee['name'],
                        ],
                    ]);
                }
            }

            // Recalculate student balance
            AccountService::recalculate($student);

            DB::commit();
            return $assessment;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
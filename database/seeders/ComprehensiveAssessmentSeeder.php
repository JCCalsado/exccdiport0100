<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Subject;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Fee;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ComprehensiveAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $schoolYear = '2025-2026';
        $semester = '1st Sem';

        // Get all active students
        $students = User::where('role', 'student')
            ->where('email', 'like', 'student%@ccdi.edu.ph')
            ->get();

        $this->command->info('Generating assessments for ' . $students->count() . ' students...');

        foreach ($students as $student) {
            // Skip if student doesn't have required fields
            if (!$student->year_level || !$student->course) {
                continue;
            }

            // Get subjects for this student
            $subjects = Subject::active()
                ->where('course', $student->course)
                ->where('year_level', $student->year_level)
                ->where('semester', $semester)
                ->get();

            if ($subjects->isEmpty()) {
                $this->command->warn("No subjects found for {$student->name}");
                continue;
            }

            // Calculate tuition and lab fees
            $tuitionFee = 0;
            $labFee = 0;
            $subjectData = [];

            foreach ($subjects as $subject) {
                $subjectCost = $subject->units * $subject->price_per_unit;
                $tuitionFee += $subjectCost;
                
                if ($subject->has_lab) {
                    $labFee += $subject->lab_fee;
                }

                $subjectData[] = [
                    'id' => $subject->id,
                    'units' => $subject->units,
                    'amount' => $subjectCost + ($subject->has_lab ? $subject->lab_fee : 0),
                ];
            }

            // Get other fees
            $otherFees = Fee::active()
                ->where('year_level', $student->year_level)
                ->where('semester', $semester)
                ->where('school_year', $schoolYear)
                ->whereIn('category', ['Library', 'Athletic', 'Miscellaneous'])
                ->get();

            $otherFeesTotal = 0;
            $feeBreakdown = [];

            foreach ($otherFees as $fee) {
                $otherFeesTotal += $fee->amount;
                $feeBreakdown[] = [
                    'id' => $fee->id,
                    'amount' => $fee->amount,
                ];
            }

            $totalAssessment = $tuitionFee + $labFee + $otherFeesTotal;

            // Create student assessment
            $assessment = StudentAssessment::create([
                'user_id' => $student->id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $student->year_level,
                'semester' => $semester,
                'school_year' => $schoolYear,
                'tuition_fee' => $tuitionFee + $labFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $totalAssessment,
                'subjects' => $subjectData,
                'fee_breakdown' => $feeBreakdown,
                'status' => 'active',
                'created_by' => 1, // Admin
            ]);

            // Create transactions for tuition (per subject)
            foreach ($subjects as $subject) {
                $subjectCost = $subject->units * $subject->price_per_unit;
                $totalSubjectCost = $subjectCost + ($subject->has_lab ? $subject->lab_fee : 0);

                Transaction::create([
                    'user_id' => $student->id,
                    'reference' => 'SUBJ-' . strtoupper(Str::random(8)),
                    'kind' => 'charge',
                    'type' => 'Tuition',
                    'year' => '2025',
                    'semester' => $semester,
                    'amount' => $totalSubjectCost,
                    'status' => 'pending',
                    'meta' => [
                        'assessment_id' => $assessment->id,
                        'subject_id' => $subject->id,
                        'subject_code' => $subject->code,
                        'subject_name' => $subject->name,
                        'units' => $subject->units,
                        'has_lab' => $subject->has_lab,
                    ],
                ]);
            }

            // Create transactions for other fees
            foreach ($otherFees as $fee) {
                Transaction::create([
                    'user_id' => $student->id,
                    'fee_id' => $fee->id,
                    'reference' => 'FEE-' . strtoupper(Str::random(8)),
                    'kind' => 'charge',
                    'type' => $fee->category,
                    'year' => '2025',
                    'semester' => $semester,
                    'amount' => $fee->amount,
                    'status' => 'pending',
                    'meta' => [
                        'assessment_id' => $assessment->id,
                        'fee_code' => $fee->code,
                        'fee_name' => $fee->name,
                    ],
                ]);
            }

            // Generate payment history for students with lower balances or fully paid
            $currentBalance = abs($student->account->balance ?? 0);
            
            if ($currentBalance < $totalAssessment) {
                $amountPaid = $totalAssessment - $currentBalance;
                $numberOfPayments = rand(1, 3);
                $paymentPerInstallment = $amountPaid / $numberOfPayments;

                for ($i = 0; $i < $numberOfPayments; $i++) {
                    $paymentAmount = $i === ($numberOfPayments - 1) 
                        ? $amountPaid - ($paymentPerInstallment * $i) // Last payment gets remainder
                        : $paymentPerInstallment;

                    $paymentDate = now()->subDays(rand(1, 60));

                    // Create payment record
                    if ($student->student) {
                        Payment::create([
                            'student_id' => $student->student->id,
                            'amount' => $paymentAmount,
                            'payment_method' => ['cash', 'gcash', 'bank_transfer'][rand(0, 2)],
                            'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                            'description' => 'Payment #' . ($i + 1),
                            'status' => Payment::STATUS_COMPLETED,
                            'paid_at' => $paymentDate,
                        ]);
                    }

                    // Create transaction record
                    Transaction::create([
                        'user_id' => $student->id,
                        'reference' => 'PAY-' . strtoupper(Str::random(8)),
                        'payment_channel' => ['cash', 'gcash', 'bank_transfer'][rand(0, 2)],
                        'kind' => 'payment',
                        'type' => 'Payment',
                        'year' => '2025',
                        'semester' => $semester,
                        'amount' => $paymentAmount,
                        'status' => 'paid',
                        'paid_at' => $paymentDate,
                        'meta' => [
                            'description' => 'Payment #' . ($i + 1),
                        ],
                    ]);
                }
            }

            // Update account balance to match calculated balance
            $student->account->update([
                'balance' => -$currentBalance
            ]);
        }

        $this->command->info('✓ Assessments and transactions generated successfully!');
        $this->command->info('✓ Payment history created for students with partial/full payments');
    }
}
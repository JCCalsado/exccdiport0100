<?php

namespace App\Services;

use App\Models\User;
use App\Models\Student;
use App\Models\Program;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\CurriculumService;

class StudentCreationService
{
    protected $curriculumService;

    public function __construct(CurriculumService $curriculumService)
    {
        $this->curriculumService = $curriculumService;
    }

    /**
     * Create a new student with all required records
     */
    public function createStudent(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $studentId = $data['student_id'] ?? $this->generateUniqueStudentId();
            $accountId = $this->generateAccountId();

            $courseName = $this->determineCourseName($data);

            $user = User::create([
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_initial' => $data['middle_initial'] ?? null,
                'email' => $data['email'],
                'birthday' => $data['birthday'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'year_level' => $data['year_level'],
                'course' => $courseName,
                'student_id' => $studentId,
                'role' => 'student',
                'status' => User::STATUS_ACTIVE,
                'password' => Hash::make('password'),
            ]);

            Student::create([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'student_id' => $studentId,
                'last_name' => $data['last_name'],
                'first_name' => $data['first_name'],
                'middle_initial' => $data['middle_initial'] ?? null,
                'email' => $data['email'],
                'course' => $courseName,
                'year_level' => $data['year_level'],
                'status' => 'enrolled',
                'birthday' => $data['birthday'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'total_balance' => 0,
            ]);

            $user->account()->create(['balance' => 0]);

            if (($data['auto_generate_assessment'] ?? false) && isset($data['program_id'])) {
                $this->generateOBEAssessment($user, $data, $accountId);
            }

            return $user->fresh(['student', 'account']);
        });
    }

    /**
     * Generate unique account_id in format ACC-YYYYMMDD-XXXX
     */
    protected function generateAccountId(): string
    {
        return DB::transaction(function () {
            $date = now()->format('Ymd');
            $prefix = "ACC-{$date}-";
            
            $lastStudent = Student::where('account_id', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                $lastNumber = intval(substr($lastStudent->account_id, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newAccountId = "{$prefix}{$newNumber}";
            
            // Ensure uniqueness
            $attempts = 0;
            while (Student::where('account_id', $newAccountId)->exists() && $attempts < 10) {
                $lastNumber = intval($newNumber);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newAccountId = "{$prefix}{$newNumber}";
                $attempts++;
            }
            
            if ($attempts >= 10) {
                throw new \Exception('Unable to generate unique account ID after multiple attempts.');
            }
            
            return $newAccountId;
        });
    }

    protected function generateUniqueStudentId(): string
    {
        $year = now()->year;
        
        return DB::transaction(function () use ($year) {
            $lastStudent = User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                $lastNumber = intval(substr($lastStudent->student_id, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newStudentId = "{$year}-{$newNumber}";
            
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

    protected function determineCourseName(array $data): string
    {
        if (isset($data['program_id']) && $data['program_id']) {
            $program = Program::find($data['program_id']);
            return $program ? $program->full_name : ($data['course'] ?? 'Unknown');
        }

        return $data['course'] ?? 'Unknown';
    }

    protected function generateOBEAssessment(User $user, array $data, string $accountId): void
    {
        try {
            $curriculum = $this->curriculumService->getCurriculumForTerm(
                $data['program_id'],
                $data['year_level'],
                $data['semester'] ?? '1st Sem',
                $data['school_year'] ?? now()->year . '-' . (now()->year + 1)
            );

            if ($curriculum) {
                $this->curriculumService->generateAssessment($user, $curriculum, $accountId);
                \Log::info('OBE assessment generated for student', [
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'curriculum_id' => $curriculum->id,
                ]);
            } else {
                \Log::warning('No curriculum found for student', [
                    'user_id' => $user->id,
                    'account_id' => $accountId,
                    'program_id' => $data['program_id'],
                    'year_level' => $data['year_level'],
                    'semester' => $data['semester'] ?? '1st Sem',
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to generate OBE assessment', [
                'user_id' => $user->id,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
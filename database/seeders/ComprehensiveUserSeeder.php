<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class ComprehensiveUserSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing student data
        Student::whereHas('user', function($q) {
            $q->where('email', 'like', 'student%@ccdi.edu.ph');
        })->delete();
        
        User::where('email', 'like', 'student%@ccdi.edu.ph')->delete();

        // Keep admin and accounting staff (from original seeder)
        $admin = User::firstOrCreate(
            ['email' => 'admin@ccdi.edu.ph'],
            [
                'last_name' => 'Rodriguez',
                'first_name' => 'Carlos',
                'middle_initial' => 'M',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => User::STATUS_ACTIVE,
                'faculty' => 'Administration',
                'phone' => '09171234501',
                'address' => 'Sorsogon City',
                'birthday' => '1985-05-15',
            ]
        );
        $admin->account()->firstOrCreate([], ['balance' => 0]);

        $accounting = User::firstOrCreate(
            ['email' => 'accounting@ccdi.edu.ph'],
            [
                'last_name' => 'Garcia',
                'first_name' => 'Ana Marie',
                'middle_initial' => 'S',
                'password' => Hash::make('password'),
                'role' => 'accounting',
                'status' => User::STATUS_ACTIVE,
                'faculty' => 'Accounting Department',
                'phone' => '09181234502',
                'address' => 'Legazpi City',
                'birthday' => '1990-08-20',
            ]
        );
        $accounting->account()->firstOrCreate([], ['balance' => 0]);

        // Filipino Names Pool
        $lastNames = [
            'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Ramos',
            'Mendoza', 'Torres', 'Flores', 'Gonzales', 'Castro',
            'Rivera', 'Bautista', 'Santiago', 'Fernandez', 'Lopez',
            'Morales', 'Aquino', 'Villanueva', 'Cruz', 'Jimenez',
            'Martinez', 'Rodriguez', 'Hernandez', 'Perez', 'Gomez'
        ];

        $firstNames = [
            // Male names
            'Juan', 'Jose', 'Pedro', 'Miguel', 'Carlos',
            'Antonio', 'Manuel', 'Francisco', 'Rafael', 'Eduardo',
            'Ricardo', 'Fernando', 'Roberto', 'Andres', 'Javier',
            // Female names
            'Maria', 'Ana', 'Carmen', 'Rosa', 'Teresa',
            'Elena', 'Isabel', 'Lucia', 'Sofia', 'Patricia',
            'Angela', 'Monica', 'Gloria', 'Diana', 'Cristina'
        ];

        $middleInitials = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V'];

        $addresses = [
            'Sorsogon City', 'Legazpi City', 'Naga City', 'Daet', 'Iriga City',
            'Tabaco City', 'Ligao City', 'Polangui', 'Daraga', 'Camalig'
        ];

        $courses = [
            'BS Electrical Engineering Technology',
            'BS Electronics Engineering Technology'
        ];

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

        $studentData = [];
        $studentNumber = 1;

        // Generate 100 students with specific distribution
        // 70 Active, 10 Dropped, 20 Graduated
        // 40 1st Year, 40 2nd Year, 10 4th Year with balance, 10 4th Year fully paid

        // 40 Active 1st Year Students
        for ($i = 0; $i < 40; $i++) {
            $studentData[] = [
                'year_level' => '1st Year',
                'status' => 'active',
                'balance' => rand(5000, 15000), // Has balance
            ];
        }

        // 30 Active 2nd Year Students (30 out of 40 total 2nd year)
        for ($i = 0; $i < 30; $i++) {
            $studentData[] = [
                'year_level' => '2nd Year',
                'status' => 'active',
                'balance' => rand(3000, 12000),
            ];
        }

        // 10 Dropped 2nd Year Students
        for ($i = 0; $i < 10; $i++) {
            $studentData[] = [
                'year_level' => '2nd Year',
                'status' => 'inactive',
                'balance' => rand(5000, 20000), // Typically have balances
            ];
        }

        // 10 Active 4th Year with Remaining Balance
        for ($i = 0; $i < 10; $i++) {
            $studentData[] = [
                'year_level' => '4th Year',
                'status' => 'active',
                'balance' => rand(1000, 5000),
            ];
        }

        // 10 Graduated 4th Year (Fully Paid)
        for ($i = 0; $i < 10; $i++) {
            $studentData[] = [
                'year_level' => '4th Year',
                'status' => 'graduated',
                'balance' => 0, // Fully paid
            ];
        }

        // Shuffle to randomize
        shuffle($studentData);

        // Status mapping
        $statusMap = [
            'active' => User::STATUS_ACTIVE,
            'graduated' => User::STATUS_GRADUATED,
            'inactive' => User::STATUS_DROPPED,
        ];

        $studentStatusMap = [
            'active' => 'enrolled',
            'graduated' => 'graduated',
            'inactive' => 'inactive',
        ];

        foreach ($studentData as $index => $data) {
            $lastName = $lastNames[array_rand($lastNames)];
            $firstName = $firstNames[array_rand($firstNames)];
            $middleInitial = $middleInitials[array_rand($middleInitials)];
            $course = $courses[array_rand($courses)];
            $address = $addresses[array_rand($addresses)];
            
            $studentId = '2025-' . str_pad($studentNumber, 4, '0', STR_PAD_LEFT);
            $email = 'student' . $studentNumber . '@ccdi.edu.ph';
            
            // Generate birthday based on year level
            $yearLevelNum = (int) substr($data['year_level'], 0, 1);
            $birthYear = 2025 - 18 - $yearLevelNum + 1; // Approximate age
            $birthday = $birthYear . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);

            $user = User::create([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_initial' => $middleInitial,
                'email' => $email,
                'password' => Hash::make('password'), // All students use 'password'
                'role' => 'student',
                'student_id' => $studentId,
                'status' => $statusMap[$data['status']],
                'course' => $course,
                'year_level' => $data['year_level'],
                'birthday' => $birthday,
                'phone' => '0917' . rand(1000000, 9999999),
                'address' => $address,
            ]);

            // ✅ Create Student with auto-generated account_id
            $student = Student::create([
                'user_id' => $user->id,
                // account_id will be auto-generated by model boot method
                'student_id' => $studentId,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_initial' => $middleInitial,
                'email' => $email,
                'course' => $course,
                'year_level' => $data['year_level'],
                'status' => $studentStatusMap[$data['status']],
                'birthday' => $birthday,
                'phone' => $user->phone,
                'address' => $address,
                'total_balance' => $data['balance'],
            ]);

            // ✅ Log the generated account_id
            \Log::info("Created student {$student->full_name} with account_id: {$student->account_id}");

            // Create account with balance
            $user->account()->create([
                'balance' => -$data['balance'] // Negative means they owe
            ]);

            // Create Student profile
            Student::create([
                'user_id' => $user->id,
                'student_id' => $studentId,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_initial' => $middleInitial,
                'email' => $email,
                'course' => $course,
                'year_level' => $data['year_level'],
                'status' => $studentStatusMap[$data['status']],
                'birthday' => $birthday,
                'phone' => $user->phone,
                'address' => $address,
                'total_balance' => $data['balance'],
            ]);

            $studentNumber++;
        }

        $this->command->info('✓ Successfully seeded 100 students:');
        $this->command->info('  - 70 Active (40 1st year, 30 2nd year, 10 4th year with balance)');
        $this->command->info('  - 10 Dropped (2nd year)');
        $this->command->info('  - 20 Graduated (4th year, fully paid)');
        $this->command->info('  - All passwords: password');
        $this->command->info('  - Email format: student1@ccdi.edu.ph to student100@ccdi.edu.ph');
    }
}
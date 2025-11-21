<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Program;
use App\Models\Course;
use App\Models\Curriculum;
use Illuminate\Support\Facades\DB;

class OBECurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $this->command->info('🎓 Seeding OBE Curriculum...');
            
            // Create Programs
            $this->createPrograms();
            
            // Create Courses
            $this->createCourses();
            
            // Create Curricula and Link Courses
            $this->createCurricula();
            
            $this->command->info('✅ OBE Curriculum seeded successfully!');
        });
    }

    private function createPrograms(): void
    {
        $this->command->info('📚 Creating Programs...');

        $programs = [
            [
                'code' => 'BSET-EET',
                'name' => 'Bachelor of Science in Engineering Technology',
                'major' => 'Electrical Engineering Technology',
                'description' => 'OBE Curriculum for Electrical Engineering Technology',
                'is_active' => true,
            ],
            [
                'code' => 'BSET-ECT',
                'name' => 'Bachelor of Engineering Technology',
                'major' => 'Electronics Engineering Technology',
                'description' => 'OBE Curriculum for Electronics Engineering Technology',
                'is_active' => true,
            ],
        ];

        foreach ($programs as $programData) {
            Program::updateOrCreate(
                ['code' => $programData['code']],
                $programData
            );
        }

        $this->command->info('✓ Programs created');
    }

    private function createCourses(): void
    {
        $this->command->info('📖 Creating Courses...');

        $electricalProgram = Program::where('code', 'BSET-EET')->first();
        $electronicsProgram = Program::where('code', 'BSET-ECT')->first();

        // Electrical Engineering Technology Courses
        $electricalCourses = [
            ['code' => 'GE1-EET', 'title' => 'Purposive Communication', 'lec' => 3, 'lab' => 0],
            ['code' => 'GEELECT1-EET', 'title' => 'Living in the IT Era', 'lec' => 2, 'lab' => 1],
            ['code' => 'GE2-EET', 'title' => 'Mathematics in the Modern World', 'lec' => 3, 'lab' => 0],
            ['code' => 'MATH101-EET', 'title' => 'Science, Technology & Society', 'lec' => 3, 'lab' => 0],
            ['code' => 'PHYS101-EET', 'title' => 'Physics for Engineering Technologists', 'lec' => 2, 'lab' => 1],
            ['code' => 'COMP101-EET', 'title' => 'Integrated Software Applications 1', 'lec' => 2, 'lab' => 1],
            ['code' => 'PATHFIT1-EET', 'title' => 'Movement Competency Training', 'lec' => 2, 'lab' => 0],
            ['code' => 'NSTP1-EET', 'title' => 'National Service Training Program 1', 'lec' => 3, 'lab' => 0],
        ];

        foreach ($electricalCourses as $courseData) {
            $totalUnits = $courseData['lec'] + $courseData['lab'];
            Course::updateOrCreate(
                ['code' => $courseData['code'], 'program_id' => $electricalProgram->id],
                [
                    'title' => $courseData['title'],
                    'lec_units' => $courseData['lec'],
                    'lab_units' => $courseData['lab'],
                    'total_units' => $totalUnits,
                    'has_lab' => $courseData['lab'] > 0,
                    'is_active' => true,
                ]
            );
        }

        // Electronics Engineering Technology Courses
        $electronicsCourses = [
            ['code' => 'GE1-ECT', 'title' => 'Purposive Communication', 'lec' => 3, 'lab' => 0],
            ['code' => 'GEELECT1-ECT', 'title' => 'Living in the IT Era', 'lec' => 2, 'lab' => 1],
            ['code' => 'GE2-ECT', 'title' => 'Mathematics in the Modern World', 'lec' => 3, 'lab' => 0],
            ['code' => 'GE3-ECT', 'title' => 'Science, Technology & Society', 'lec' => 3, 'lab' => 0],
            ['code' => 'ELXT110', 'title' => 'Basic Electricity and Electronics', 'lec' => 3, 'lab' => 1],
            ['code' => 'MATH101-ECT', 'title' => 'Calculus 1 - Differential Calculus', 'lec' => 3, 'lab' => 0],
            ['code' => 'PHYS101-ECT', 'title' => 'Physics for Engineering Technologies', 'lec' => 3, 'lab' => 0],
            ['code' => 'COMP101-ECT', 'title' => 'Integrated Software Applications', 'lec' => 2, 'lab' => 1],
            ['code' => 'PATHFIT1-ECT', 'title' => 'Movement Competency Training', 'lec' => 2, 'lab' => 0],
            ['code' => 'NSTP1-ECT', 'title' => 'National Service Training Program 1', 'lec' => 3, 'lab' => 0],
        ];

        foreach ($electronicsCourses as $courseData) {
            $totalUnits = $courseData['lec'] + $courseData['lab'];
            Course::updateOrCreate(
                ['code' => $courseData['code'], 'program_id' => $electronicsProgram->id],
                [
                    'title' => $courseData['title'],
                    'lec_units' => $courseData['lec'],
                    'lab_units' => $courseData['lab'],
                    'total_units' => $totalUnits,
                    'has_lab' => $courseData['lab'] > 0,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('✓ Courses created');
    }

    private function createCurricula(): void
    {
        $this->command->info('📋 Creating Curricula...');

        $electricalProgram = Program::where('code', 'BSET-EET')->first();
        $electronicsProgram = Program::where('code', 'BSET-ECT')->first();

        $tuitionPerUnit = 364.00;
        $labFee = 1656.00;
        $registrationFee = 200.00;
        $miscFee = 1200.00;

        // Electrical Engineering - 1st Year, 1st Sem
        $electricalCurriculum = Curriculum::updateOrCreate(
            [
                'program_id' => $electricalProgram->id,
                'school_year' => '2025-2026',
                'year_level' => '1st Year',
                'semester' => '1st Sem',
            ],
            [
                'tuition_per_unit' => $tuitionPerUnit,
                'lab_fee' => $labFee,
                'registration_fee' => $registrationFee,
                'misc_fee' => $miscFee,
                'term_count' => 5,
                'notes' => 'OBE Curriculum - 15% increase from previous year',
                'is_active' => true,
            ]
        );

        // Link Electrical courses
        $electricalCourses = Course::where('program_id', $electricalProgram->id)->get();
        $order = 1;
        foreach ($electricalCourses as $course) {
            $electricalCurriculum->courses()->syncWithoutDetaching([
                $course->id => ['order' => $order++]
            ]);
        }

        // Electronics Engineering - 1st Year, 1st Sem
        $electronicsCurriculum = Curriculum::updateOrCreate(
            [
                'program_id' => $electronicsProgram->id,
                'school_year' => '2025-2026',
                'year_level' => '1st Year',
                'semester' => '1st Sem',
            ],
            [
                'tuition_per_unit' => $tuitionPerUnit,
                'lab_fee' => $labFee,
                'registration_fee' => $registrationFee,
                'misc_fee' => $miscFee,
                'term_count' => 5,
                'notes' => 'OBE Curriculum - 15% increase from previous year',
                'is_active' => true,
            ]
        );

        // Link Electronics courses
        $electronicsCourses = Course::where('program_id', $electronicsProgram->id)->get();
        $order = 1;
        foreach ($electronicsCourses as $course) {
            $electronicsCurriculum->courses()->syncWithoutDetaching([
                $course->id => ['order' => $order++]
            ]);
        }

        $this->command->info('✓ Curricula created and linked to courses');

        // Display summary
        $this->displaySummary($electricalCurriculum, 'Electrical Engineering Technology');
        $this->displaySummary($electronicsCurriculum, 'Electronics Engineering Technology');
    }

    private function displaySummary(Curriculum $curriculum, string $programName): void
    {
        $totalUnits = $curriculum->courses->sum('total_units');
        $tuition = $curriculum->calculateTuition();
        $labFees = $curriculum->calculateLabFees();
        $total = $curriculum->calculateTotalAssessment();

        $this->command->newLine();
        $this->command->info("=== {$programName} ===");
        $this->command->table(
            ['Item', 'Value'],
            [
                ['Total Units', $totalUnits],
                ['Courses', $curriculum->courses->count()],
                ['Tuition Fee', '₱' . number_format($tuition, 2)],
                ['Lab Fees', '₱' . number_format($labFees, 2)],
                ['Registration', '₱' . number_format($curriculum->registration_fee, 2)],
                ['Misc Fee', '₱' . number_format($curriculum->misc_fee, 2)],
                ['Total Assessment', '₱' . number_format($total, 2)],
            ]
        );
    }
}
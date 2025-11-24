<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Program;
use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CurriculaController extends Controller
{
    /**
     * Display a listing of curricula
     */
    public function index(Request $request)
    {
        $query = Curriculum::with(['program', 'courses']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('school_year', 'like', "%{$search}%")
                    ->orWhereHas('program', function ($programQuery) use ($search) {
                        $programQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('major', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('program')) {
            $query->where('program_id', $request->program);
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        $curricula = $query->orderBy('school_year', 'desc')
            ->orderByRaw("FIELD(year_level, '1st Year', '2nd Year', '3rd Year', '4th Year')")
            ->orderByRaw("FIELD(semester, '1st Sem', '2nd Sem', 'Summer')")
            ->paginate(15)
            ->withQueryString();

        // Add calculated fields to each curriculum
        $curricula->getCollection()->transform(function ($curriculum) {
            $curriculum->courses_count = $curriculum->courses->count();
            $curriculum->total_units = $curriculum->courses->sum('total_units');
            $curriculum->total_assessment = $curriculum->calculateTotalAssessment();
            return $curriculum;
        });

        // Get filter options
        $programs = Program::where('is_active', true)->orderBy('code')->get();
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        $schoolYears = Curriculum::select('school_year')
            ->distinct()
            ->orderBy('school_year', 'desc')
            ->pluck('school_year');

        return Inertia::render('Curricula/Index', [
            'curricula' => $curricula,
            'filters' => $request->only(['search', 'program', 'year_level', 'semester', 'school_year']),
            'programs' => $programs,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Show the form for creating a new curriculum
     */
    public function create()
    {
        $programs = Program::where('is_active', true)->orderBy('code')->get();
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];

        // Generate school year options
        $currentYear = now()->year;
        $schoolYears = [];
        for ($i = 0; $i < 5; $i++) {
            $year = $currentYear + $i;
            $schoolYears[] = "{$year}-" . ($year + 1);
        }

        return Inertia::render('Curricula/Create', [
            'programs' => $programs,
            'courses' => [], // Will be loaded via AJAX
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Store a newly created curriculum
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'school_year' => 'required|string',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'tuition_per_unit' => 'required|numeric|min:0',
            'lab_fee' => 'required|numeric|min:0',
            'registration_fee' => 'required|numeric|min:0',
            'misc_fee' => 'required|numeric|min:0',
            'term_count' => 'required|integer|min:1|max:10',
            'courses' => 'required|array|min:1',
            'courses.*' => 'exists:courses,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Create curriculum
            $curriculum = Curriculum::create([
                'program_id' => $validated['program_id'],
                'school_year' => $validated['school_year'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'tuition_per_unit' => $validated['tuition_per_unit'],
                'lab_fee' => $validated['lab_fee'],
                'registration_fee' => $validated['registration_fee'],
                'misc_fee' => $validated['misc_fee'],
                'term_count' => $validated['term_count'],
                'notes' => $validated['notes'],
                'is_active' => true,
            ]);

            // Attach courses with order
            $order = 1;
            foreach ($validated['courses'] as $courseId) {
                $curriculum->courses()->attach($courseId, ['order' => $order++]);
            }

            DB::commit();

            return redirect()
                ->route('curricula.show', $curriculum->id)
                ->with('success', 'Curriculum created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create curriculum: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified curriculum
     */
    public function show(Curriculum $curriculum)
    {
        $curriculum->load(['program', 'courses', 'studentCurricula.user']);
        
        // Calculate totals
        $totalUnits = $curriculum->courses->sum('total_units');
        $tuition = $curriculum->calculateTuition();
        $labFees = $curriculum->calculateLabFees();
        $totalAssessment = $curriculum->calculateTotalAssessment();
        $paymentTerms = $curriculum->generatePaymentTerms();

        // Get enrolled students count
        $enrolledStudentsCount = $curriculum->studentCurricula()->active()->count();

        return Inertia::render('Curricula/Show', [
            'curriculum' => $curriculum,
            'totals' => [
                'total_units' => $totalUnits,
                'tuition' => $tuition,
                'lab_fees' => $labFees,
                'total_assessment' => $totalAssessment,
            ],
            'paymentTerms' => $paymentTerms,
            'enrolledStudentsCount' => $enrolledStudentsCount,
        ]);
    }

    /**
     * Show the form for editing the specified curriculum
     */
    public function edit(Curriculum $curriculum)
    {
        $curriculum->load(['program', 'courses']);
        
        $programs = Program::where('is_active', true)->orderBy('code')->get();
        $courses = Course::where('program_id', $curriculum->program_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        
        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];

        // Generate school year options
        $currentYear = now()->year;
        $schoolYears = [];
        for ($i = -2; $i < 5; $i++) {
            $year = $currentYear + $i;
            $schoolYears[] = "{$year}-" . ($year + 1);
        }

        // Get selected course IDs
        $selectedCourses = $curriculum->courses->pluck('id')->toArray();

        return Inertia::render('Curricula/Edit', [
            'curriculum' => $curriculum,
            'programs' => $programs,
            'courses' => $courses,
            'selectedCourses' => $selectedCourses,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Update the specified curriculum
     */
    public function update(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'school_year' => 'required|string',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'tuition_per_unit' => 'required|numeric|min:0',
            'lab_fee' => 'required|numeric|min:0',
            'registration_fee' => 'required|numeric|min:0',
            'misc_fee' => 'required|numeric|min:0',
            'term_count' => 'required|integer|min:1|max:10',
            'courses' => 'required|array|min:1',
            'courses.*' => 'exists:courses,id',
            'notes' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            // Update curriculum
            $curriculum->update([
                'program_id' => $validated['program_id'],
                'school_year' => $validated['school_year'],
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'tuition_per_unit' => $validated['tuition_per_unit'],
                'lab_fee' => $validated['lab_fee'],
                'registration_fee' => $validated['registration_fee'],
                'misc_fee' => $validated['misc_fee'],
                'term_count' => $validated['term_count'],
                'notes' => $validated['notes'],
                'is_active' => $validated['is_active'] ?? $curriculum->is_active,
            ]);

            // Sync courses with order
            $coursesWithOrder = [];
            foreach ($validated['courses'] as $index => $courseId) {
                $coursesWithOrder[$courseId] = ['order' => $index + 1];
            }
            $curriculum->courses()->sync($coursesWithOrder);

            DB::commit();

            return redirect()
                ->route('curricula.show', $curriculum->id)
                ->with('success', 'Curriculum updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update curriculum: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified curriculum
     */
    public function destroy(Curriculum $curriculum)
    {
        // Check if curriculum has enrolled students
        if ($curriculum->studentCurricula()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete curriculum with enrolled students. Deactivate it instead.'
            ]);
        }

        $curriculum->delete();

        return redirect()
            ->route('curricula.index')
            ->with('success', 'Curriculum deleted successfully!');
    }

    /**
     * Toggle curriculum active status
     */
    public function toggleStatus(Curriculum $curriculum)
    {
        $curriculum->update(['is_active' => !$curriculum->is_active]);

        return back()->with('success', 'Curriculum status updated successfully!');
    }

    /**
     * Get courses for a specific program (AJAX)
     */
    public function getCourses(Request $request)
    {
        $programId = $request->input('program_id');
        
        if (!$programId) {
            return response()->json([]);
        }
        
        $courses = Course::where('program_id', $programId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'lec_units' => $course->lec_units,
                    'lab_units' => $course->lab_units,
                    'total_units' => $course->total_units,
                    'has_lab' => $course->has_lab,
                ];
            });

        return response()->json($courses);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Payment;
use App\Models\Fee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    // Display all students
    public function index(Request $request)
    {
        $query = Student::with(['payments', 'transactions', 'account']);

        // Enhanced search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('student_id', 'like', '%' . $searchTerm . '%')
                  ->orWhere('course', 'like', '%' . $searchTerm . '%')
                  ->orWhere('year_level', 'like', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'like', '%' . $searchTerm . '%')
                  ->orWhere('address', 'like', '%' . $searchTerm . '%');
            });
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(10);

        // Try to load fees from DB, otherwise fallback defaults
        $fees = Fee::active()->select('name', 'amount')->get();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters'  => $request->only('search'),
            'fees'     => $fees->values(), // pass fee breakdown
        ]);
    }

    // Show create student form
    public function create()
    {
        return Inertia::render('Students/Create');
    }

    // Store new student
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|unique:students,student_id',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email|unique:users,email',
            'course' => 'required|string|max:255',
            'year_level' => 'required|string',
            'birthday' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'total_balance' => 'required|numeric|min:0',
        ]);

        // Split name into first/last
        $parts = explode(' ', $validated['name'], 2);
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? 'N/A';

        // Create corresponding USER
        $user = \App\Models\User::create([
            'student_id' => $validated['student_id'],
            'first_name' => $first,
            'last_name' => $last,
            'middle_initial' => null,

            'email' => $validated['email'],
            'password' => bcrypt('password123'), // You may change this
            'role' => 'student',
            'status' => 'active',

            'birthday' => $validated['birthday'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'course' => $validated['course'],
            'year_level' => $validated['year_level'],
        ]);

        // Now create the Student and link to user_id
        $validated['user_id'] = $user->id;

        Student::create($validated);

        return redirect()
            ->route('students.index')
            ->with('success', 'Student created successfully!');
    }

    // Show single student  
    public function show($id)
    {
        $student = Student::with(['payments', 'user.account'])->findOrFail($id);

        return Inertia::render('Students/StudentProfile', [
            'student' => $student,
        ]);
    }


    // Store payment for student
    public function storePayment(Request $request, Student $student)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'status' => 'required|string',
            'paid_at' => 'required|date',
        ]);

        $user = $request->user();

        // Student can only pay for their own record
        if ($user->role === 'student') {
            if (!$user->student || $user->student->id !== $student->id) {
                abort(403, 'Unauthorized payment submission.');
            }
        }

        $student->payments()->create($request->only([
            'amount', 'description', 'payment_method', 'reference_number', 'status', 'paid_at'
        ]));

        return back()->with('success', 'Payment recorded successfully!');
    }


    // Delete student
    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted successfully!');
    }

    // Show edit form
    public function edit(Student $student)
    {
        return Inertia::render('Students/Edit', [
            'student' => $student,
        ]);
    }

    // Update student
    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|unique:students,student_id,' . $student->id,
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'course' => 'required|string|max:255',
            'year_level' => 'required|string',
            'birthday' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'total_balance' => 'required|numeric|min:0',
        ]);

        $student->update($validated);

        return redirect()->route('students.index')->with('success', 'Student updated successfully!');
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->with('payments')->firstOrFail();

        return Inertia::render('Student/Profile', [
            'student' => $student,
        ]);
    }
}
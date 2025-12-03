<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Student;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin' || $this->user()->role === 'accounting';
    }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;

        return [
            // ✅ NEW: account_id validation (if updating)
            'account_id' => [
                'sometimes',
                'string',
                'size:18', // ACC-YYYYMMDD-XXXX = 18 chars
                'regex:/^ACC-\d{8}-\d{4}$/',
                Rule::unique('students', 'account_id')->ignore($studentId),
            ],

            // Personal Information
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:10'],
            'email' => ['required', 'email', Rule::unique('students', 'email')->ignore($studentId)],
            'birthday' => ['required', 'date', 'before:today', 'after:1900-01-01'],

            // Contact Information
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9\s\-\+\(\)]*$/'],
            'address' => ['required', 'string', 'max:500'],

            // Academic Information
            'student_id' => ['nullable', 'string', 'max:50', Rule::unique('students', 'student_id')->ignore($studentId)],
            'program_id' => ['nullable', 'exists:programs,id'],
            'course' => ['nullable', 'string', 'max:255'],
            'year_level' => ['required', 'string', Rule::in(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
            'semester' => ['nullable', 'string', Rule::in(['1st Sem', '2nd Sem', 'Summer'])],
            'school_year' => ['nullable', 'string', 'max:20'],

            // Options
            'auto_generate_assessment' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'account_id.regex' => 'Account ID must be in format ACC-YYYYMMDD-XXXX',
            'account_id.size' => 'Account ID must be exactly 18 characters',
            'account_id.unique' => 'This account ID is already in use',

            'last_name.required' => 'Last name is required.',
            'first_name.required' => 'First name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'birthday.required' => 'Birthday is required.',
            'birthday.before' => 'Birthday must be a date before today.',
            'phone.required' => 'Phone number is required.',
            'phone.regex' => 'Please enter a valid phone number.',
            'address.required' => 'Address is required.',
            'year_level.required' => 'Year level is required.',
            'year_level.in' => 'Invalid year level selected.',
            'student_id.unique' => 'This student ID is already in use.',
            'program_id.exists' => 'Selected program does not exist.',
        ];
    }

    public function attributes(): array
    {
        return [
            'last_name' => 'last name',
            'first_name' => 'first name',
            'middle_initial' => 'middle initial',
            'student_id' => 'student ID',
            'year_level' => 'year level',
            'school_year' => 'school year',
        ];
    }
}
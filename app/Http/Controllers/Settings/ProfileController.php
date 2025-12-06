<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('student');
        
        // ✅ Include account_id for students
        $userData = [
            'id' => $user->id,
            'last_name' => $user->last_name,
            'first_name' => $user->first_name,
            'middle_initial' => $user->middle_initial,
            'email' => $user->email,
            'birthday' => $user->birthday?->format('Y-m-d'),
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role,
            'status' => $user->status,
        ];
        
        // ✅ Add student-specific fields including account_id
        if ($user->isStudent() && $user->student) {
            $userData['account_id'] = $user->student->account_id; // ✅ PRIMARY
            $userData['student_id'] = $user->student_id;
            $userData['course'] = $user->course;
            $userData['year_level'] = $user->year_level;
        }
        
        // ✅ Add staff-specific fields
        if ($user->hasRole(['admin', 'accounting'])) {
            $userData['faculty'] = $user->faculty;
        }

        return Inertia::render('Settings/Profile', [
            'user' => $userData,
            'mustVerifyEmail' => method_exists($user, 'hasVerifiedEmail')
                ? !$user->hasVerifiedEmail()
                : false,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            // ✅ Update users table
            $userUpdateData = [
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'email' => $validated['email'],
                'birthday' => $validated['birthday'] ?? $user->birthday,
                'phone' => $validated['phone'] ?? $user->phone,
                'address' => $validated['address'] ?? $user->address,
            ];

            // ✅ Add student-specific fields to users table
            if ($user->isStudent()) {
                $userUpdateData['student_id'] = $validated['student_id'] ?? $user->student_id;
                $userUpdateData['course'] = $validated['course'];
                $userUpdateData['year_level'] = $validated['year_level'];
                
                // ✅ Only admin can change student status
                if (isset($validated['status']) && $request->user()->isAdmin()) {
                    $userUpdateData['status'] = $validated['status'];
                }
            }

            // ✅ Add faculty for accounting/admin
            if ($user->hasRole(['admin', 'accounting'])) {
                $userUpdateData['faculty'] = $validated['faculty'] ?? $user->faculty;
            }

            // Update the user
            $user->fill($userUpdateData);
            $user->save();

            // ✅ Update students table if user is a student
            // ⚠️ CRITICAL: Do NOT allow account_id to be changed
            if ($user->isStudent() && $user->student) {
                $statusMap = [
                    'active' => 'enrolled',
                    'graduated' => 'graduated',
                    'dropped' => 'inactive',
                ];

                $studentData = [
                    // ✅ CRITICAL: account_id is READ-ONLY, never updated
                    'last_name' => $validated['last_name'],
                    'first_name' => $validated['first_name'],
                    'middle_initial' => $validated['middle_initial'] ?? null,
                    'student_id' => $validated['student_id'] ?? $user->student->student_id,
                    'email' => $validated['email'],
                    'birthday' => $validated['birthday'] ?? $user->student->birthday,
                    'phone' => $validated['phone'] ?? $user->student->phone,
                    'address' => $validated['address'] ?? $user->student->address,
                    'course' => $validated['course'],
                    'year_level' => $validated['year_level'],
                ];

                // Update status if provided and user is admin
                if (isset($validated['status']) && $request->user()->isAdmin()) {
                    $studentData['status'] = $statusMap[$validated['status']] ?? $user->student->status;
                }

                $user->student->update($studentData);

                Log::info('Student record updated', [
                    'account_id' => $user->student->account_id, // ✅ Log with account_id
                    'user_id' => $user->id,
                    'updated_fields' => array_keys($studentData),
                ]);
            }

            DB::commit();

            Log::info('User profile updated', [
                'user_id' => $user->id,
                'account_id' => $user->student->account_id ?? null, // ✅ Include in logs
                'updated_by' => $request->user()->id,
            ]);

            return Redirect::route('profile.edit')
                ->with('success', 'Profile updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Profile update failed', [
                'user_id' => $user->id,
                'account_id' => $user->student->account_id ?? null,
                'error' => $e->getMessage(),
            ]);

            return Redirect::back()
                ->withErrors(['error' => 'Failed to update profile.'])
                ->withInput();
        }
    }

    /**
     * Update profile picture.
     */
    public function updatePicture(Request $request): RedirectResponse
    {
        $request->validate([
            'profile_picture' => 'required|image|max:2048',
        ]);

        $user = $request->user();

        DB::beginTransaction();
        try {
            // Delete old file if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $path = $request->file('profile_picture')->store('profile_pictures', 'public');

            $user->update(['profile_picture' => $path]);

            DB::commit();

            return Redirect::back()->with('success', 'Profile picture updated.');

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->withErrors(['error' => 'Failed to update profile picture.']);
        }
    }

    /**
     * Remove profile picture.
     */
    public function removePicture(Request $request): RedirectResponse
    {
        $user = $request->user();

        DB::beginTransaction();
        try {
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
                $user->update(['profile_picture' => null]);
            }

            DB::commit();

            return Redirect::back()->with('success', 'Profile picture removed.');

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->withErrors(['error' => 'Failed to remove profile picture.']);
        }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // ✅ CRITICAL: Students with account_id cannot be deleted
        $user = $request->user();
        
        if ($user->isStudent() && $user->student && $user->student->account_id) {
            Log::warning('Attempted to delete student with account_id', [
                'account_id' => $user->student->account_id,
                'user_id' => $user->id,
                'attempted_by' => $request->user()->id,
            ]);
            
            return Redirect::back()->withErrors([
                'error' => 'Student accounts cannot be deleted. Please contact administration to deactivate your account.'
            ]);
        }

        // For non-students or students without account_id (shouldn't exist)
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        DB::beginTransaction();
        try {
            $user->delete();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            DB::commit();

            return Redirect::to('/');

        } catch (\Exception $e) {
            DB::rollBack();
            return Redirect::back()->withErrors(['error' => 'Failed to delete account.']);
        }
    }
}
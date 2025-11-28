<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        return match($user->role) {
            'student' => redirect()->route('student.dashboard'),
            'accounting' => redirect()->route('accounting.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            default => abort(403, 'Invalid user role'),
        };
    }
}
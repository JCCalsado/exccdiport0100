<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AssessmentDataService;
use App\Models\Payment;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // ✅ USE UNIFIED DATA SERVICE
        $data = AssessmentDataService::getUnifiedAssessmentData($user);

        // ✅ ADD MISSING PAYMENTS DATA
        if ($user->student) {
            $data['payments'] = Payment::where('student_id', $user->student->id)
                ->orderBy('paid_at', 'desc')
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'amount' => (float) $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'reference_number' => $payment->reference_number,
                        'description' => $payment->description,
                        'status' => $payment->status,
                        'paid_at' => $payment->paid_at?->toISOString(),
                        'created_at' => $payment->created_at?->toISOString(),
                    ];
                })
                ->toArray();
        } else {
            $data['payments'] = [];
        }

        return Inertia::render('Student/AccountOverview', array_merge($data, [
            'tab' => request('tab', 'fees'),
        ]));
    }
}
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Notifications\PaymentCreated;
use App\Notifications\PaymentReceived;

class PaymentController extends Controller
{
    public function index(Job $job): JsonResponse
    {
        // Ensure user is part of the job
        if ($job->user_id !== auth()->id() && 
            !$job->applications()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payments = Payment::where('job_id', $job->id)
            ->latest()
            ->get();

        return response()->json($payments);
    }

    public function store(Request $request, Job $job): JsonResponse
    {
        // Ensure job is not completed
        if ($job->status === 'completed') {
            return response()->json(['message' => 'Cannot create payments for completed jobs'], 403);
        }

        // Ensure user is the job owner
        if ($job->user_id !== auth()->id()) {
            return response()->json(['message' => 'Only the job owner can create payments'], 403);
        }

        // Get the developer (payee) from the accepted application
        $application = $job->applications()
            ->where('status', 'accepted')
            ->with(['user', 'job'])
            ->first();

        if (!$application) {
            return response()->json(['message' => 'No accepted application found for this job'], 422);
        }

        // Create validator instance
        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date', 'after:today']
        ]);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        // Check if total payments would exceed job budget
        $currentTotal = Payment::totalForJob($job->id);
        if (($currentTotal + $validated['amount']) > $job->budget) {
            return response()->json([
                'errors' => [
                    'amount' => ['Total payments cannot exceed job budget']
                ]
            ], 422);
        }

        // Create payment
        $payment = Payment::create([
            'job_id' => $job->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'status' => 'pending',
            'payer_id' => auth()->id(),
            'payee_id' => $application->user_id,
            'due_date' => $validated['due_date']
        ]);

        // Load relationships
        $payment->load(['job', 'payee', 'payer']);

        // Debug assertions
        if (!$application->user) {
            \Log::error('Application user is null', [
                'application_id' => $application->id,
                'user_id' => $application->user_id
            ]);
            throw new \RuntimeException('Application user is null');
        }

        // Notify developer
        try {
            $application->user->notify(new PaymentCreated($payment));
        } catch (\Exception $e) {
            \Log::error('Failed to send PaymentCreated notification', [
                'error' => $e->getMessage(),
                'application_id' => $application->id,
                'user_id' => $application->user_id
            ]);
            throw $e;
        }

        return response()->json($payment, 201);
    }

    public function update(Request $request, Payment $payment): JsonResponse
    {
        // Ensure user is the payer
        if ($payment->payer_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate request
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,paid'],
            'transaction_id' => ['required_if:status,paid', 'string']
        ]);

        // Update payment
        $payment->update([
            'status' => $validated['status'],
            'transaction_id' => $validated['transaction_id'] ?? null,
            'paid_at' => $validated['status'] === 'paid' ? now() : null
        ]);

        // Load relationships
        $payment->load(['job', 'payee', 'payer']);

        // If payment is marked as paid, notify the payer (client)
        if ($validated['status'] === 'paid') {
            $payment->payer->notify(new PaymentReceived($payment));
        }

        return response()->json($payment);
    }
} 
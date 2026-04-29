<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\LoanCharge;
use Illuminate\Http\Request;

class LoanChargeController extends Controller
{
    public function index(Loan $loan)
    {
        return response()->json($loan->charges()->orderByDesc('created_at')->get());
    }

    public function store(Request $request, Loan $loan)
    {
        $this->authorise($request, $loan);

        $validated = $request->validate([
            'charge_type' => 'required|in:processing_fee,insurance,other',
            'name'        => 'required|string|max:100',
            'amount'      => 'required|numeric|min:0',
        ]);

        $charge = $loan->charges()->create($validated);

        return response()->json($charge, 201);
    }

    public function markPaid(Request $request, Loan $loan, LoanCharge $charge)
    {
        $this->authorise($request, $loan);

        $charge->update(['is_paid' => true, 'paid_at' => now()]);

        return response()->json($charge->fresh());
    }

    public function destroy(Request $request, Loan $loan, LoanCharge $charge)
    {
        $this->authorise($request, $loan);
        $charge->delete();

        return response()->json(['message' => 'Charge deleted.']);
    }

    private function authorise(Request $request, Loan $loan): void
    {
        if ($loan->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}

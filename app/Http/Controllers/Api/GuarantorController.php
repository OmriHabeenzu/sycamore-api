<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guarantor;
use App\Models\Loan;
use Illuminate\Http\Request;

class GuarantorController extends Controller
{
    public function index(Loan $loan)
    {
        return response()->json($loan->guarantors()->with('borrower')->get());
    }

    public function store(Request $request, Loan $loan)
    {
        $this->authorise($request, $loan);

        $validated = $request->validate([
            'borrower_id'    => 'nullable|exists:borrowers,id',
            'name'           => 'required|string|max:100',
            'phone'          => 'required|string|max:20',
            'national_id'    => 'nullable|string|max:50',
            'relationship'   => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:255',
            'employer'       => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        $guarantor = $loan->guarantors()->create($validated);

        return response()->json($guarantor->load('borrower'), 201);
    }

    public function update(Request $request, Loan $loan, Guarantor $guarantor)
    {
        $this->authorise($request, $loan);

        $validated = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'phone'          => 'sometimes|string|max:20',
            'national_id'    => 'nullable|string|max:50',
            'relationship'   => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:255',
            'employer'       => 'nullable|string|max:100',
            'monthly_income' => 'nullable|numeric|min:0',
        ]);

        $guarantor->update($validated);

        return response()->json($guarantor->fresh());
    }

    public function destroy(Request $request, Loan $loan, Guarantor $guarantor)
    {
        $this->authorise($request, $loan);
        $guarantor->delete();
        return response()->json(['message' => 'Guarantor removed.']);
    }

    private function authorise(Request $request, Loan $loan): void
    {
        if ($loan->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}

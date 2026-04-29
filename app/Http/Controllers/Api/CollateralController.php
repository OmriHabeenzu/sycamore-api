<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collateral;
use App\Models\Loan;
use Illuminate\Http\Request;

class CollateralController extends Controller
{
    public function index(Loan $loan)
    {
        return response()->json($loan->collateral()->get());
    }

    public function store(Request $request, Loan $loan)
    {
        $this->authorise($request, $loan);

        $validated = $request->validate([
            'type'            => 'required|in:property,vehicle,equipment,inventory,other',
            'description'     => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0',
            'serial_number'   => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $collateral = $loan->collateral()->create($validated);

        return response()->json($collateral, 201);
    }

    public function update(Request $request, Loan $loan, Collateral $collateral)
    {
        $this->authorise($request, $loan);

        $validated = $request->validate([
            'type'            => 'sometimes|in:property,vehicle,equipment,inventory,other',
            'description'     => 'sometimes|string|max:255',
            'estimated_value' => 'sometimes|numeric|min:0',
            'serial_number'   => 'nullable|string|max:100',
            'location'        => 'nullable|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $collateral->update($validated);

        return response()->json($collateral->fresh());
    }

    public function destroy(Request $request, Loan $loan, Collateral $collateral)
    {
        $this->authorise($request, $loan);
        $collateral->delete();
        return response()->json(['message' => 'Collateral removed.']);
    }

    private function authorise(Request $request, Loan $loan): void
    {
        if ($loan->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}

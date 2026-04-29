<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanProduct;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function index(Request $request)
    {
        $products = LoanProduct::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'                 => 'required|string|max:100',
            'description'          => 'nullable|string',
            'interest_method'      => 'required|in:flat_rate,reducing_balance',
            'interest_rate'        => 'required|numeric|min:0|max:100',
            'repayment_frequency'  => 'required|in:daily,weekly,biweekly,monthly,quarterly',
            'min_amount'           => 'required|numeric|min:0',
            'max_amount'           => 'nullable|numeric|min:0',
            'min_term'             => 'required|integer|min:1',
            'max_term'             => 'nullable|integer|min:1',
            'term_unit'            => 'required|in:days,weeks,months',
            'processing_fee_type'  => 'required|in:fixed,percentage',
            'processing_fee_value' => 'required|numeric|min:0',
            'late_penalty_type'    => 'required|in:fixed,percentage_of_outstanding',
            'late_penalty_value'   => 'required|numeric|min:0',
            'grace_period_days'    => 'required|integer|min:0',
            'is_active'            => 'boolean',
        ]);

        $product = LoanProduct::create(array_merge($validated, [
            'company_id' => $request->user()->company_id,
        ]));

        return response()->json($product, 201);
    }

    public function show(Request $request, LoanProduct $loanProduct)
    {
        $this->authorizeCompany($request, $loanProduct->company_id);

        return response()->json($loanProduct);
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $this->authorizeCompany($request, $loanProduct->company_id);

        $validated = $request->validate([
            'name'                 => 'sometimes|string|max:100',
            'description'          => 'nullable|string',
            'interest_method'      => 'sometimes|in:flat_rate,reducing_balance',
            'interest_rate'        => 'sometimes|numeric|min:0|max:100',
            'repayment_frequency'  => 'sometimes|in:daily,weekly,biweekly,monthly,quarterly',
            'min_amount'           => 'sometimes|numeric|min:0',
            'max_amount'           => 'nullable|numeric|min:0',
            'min_term'             => 'sometimes|integer|min:1',
            'max_term'             => 'nullable|integer|min:1',
            'term_unit'            => 'sometimes|in:days,weeks,months',
            'processing_fee_type'  => 'sometimes|in:fixed,percentage',
            'processing_fee_value' => 'sometimes|numeric|min:0',
            'late_penalty_type'    => 'sometimes|in:fixed,percentage_of_outstanding',
            'late_penalty_value'   => 'sometimes|numeric|min:0',
            'grace_period_days'    => 'sometimes|integer|min:0',
            'is_active'            => 'boolean',
        ]);

        $loanProduct->update($validated);

        return response()->json($loanProduct);
    }

    public function destroy(Request $request, LoanProduct $loanProduct)
    {
        $this->authorizeCompany($request, $loanProduct->company_id);

        if ($loanProduct->loans()->exists()) {
            return response()->json(
                ['message' => 'Cannot delete a product that has loans attached.'], 422
            );
        }

        $loanProduct->delete();

        return response()->json(['message' => 'Loan product deleted.']);
    }

    private function authorizeCompany(Request $request, int $companyId): void
    {
        if ($request->user()->company_id !== $companyId && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}

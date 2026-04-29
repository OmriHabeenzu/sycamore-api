<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use Illuminate\Http\Request;

class BorrowerController extends Controller
{
    public function index(Request $request)
    {
        $query = Borrower::where('company_id', $request->user()->company_id)
            ->with('nextOfKin');

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('borrower_no', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'        => 'required|string|max:100',
            'last_name'         => 'required|string|max:100',
            'phone'             => 'required|string|max:20',
            'email'             => 'nullable|email|max:100',
            'dob'               => 'nullable|date',
            'gender'            => 'nullable|in:male,female,other',
            'national_id'       => 'nullable|string|max:50',
            'address'           => 'nullable|string|max:255',
            'city'              => 'nullable|string|max:100',
            'employment_status' => 'nullable|in:employed,self_employed,unemployed',
            'employer'          => 'nullable|string|max:100',
            'monthly_income'    => 'nullable|numeric|min:0',
            'next_of_kin'       => 'nullable|array',
            'next_of_kin.*.name'         => 'required|string|max:100',
            'next_of_kin.*.relationship' => 'required|string|max:50',
            'next_of_kin.*.phone'        => 'required|string|max:20',
            'next_of_kin.*.address'      => 'nullable|string|max:255',
        ]);

        $companyId = $request->user()->company_id;

        // Generate borrower_no: BRW-0001
        $last = Borrower::where('company_id', $companyId)
            ->orderBy('id', 'desc')->first();
        $next = $last ? ((int) substr($last->borrower_no, 4)) + 1 : 1;
        $borrowerNo = 'BRW-' . str_pad($next, 4, '0', STR_PAD_LEFT);

        $borrower = Borrower::create(array_merge($validated, [
            'company_id'  => $companyId,
            'borrower_no' => $borrowerNo,
            'created_by'  => $request->user()->id,
        ]));

        if (!empty($validated['next_of_kin'])) {
            $borrower->nextOfKin()->createMany($validated['next_of_kin']);
        }

        return response()->json($borrower->load('nextOfKin'), 201);
    }

    public function show(Request $request, Borrower $borrower)
    {
        $this->authorizeCompany($request, $borrower->company_id);

        return response()->json(
            $borrower->load(['nextOfKin', 'loans.loanProduct', 'documents'])
        );
    }

    public function update(Request $request, Borrower $borrower)
    {
        $this->authorizeCompany($request, $borrower->company_id);

        $validated = $request->validate([
            'first_name'        => 'sometimes|string|max:100',
            'last_name'         => 'sometimes|string|max:100',
            'phone'             => 'sometimes|string|max:20',
            'email'             => 'nullable|email|max:100',
            'dob'               => 'nullable|date',
            'gender'            => 'nullable|in:male,female,other',
            'national_id'       => 'nullable|string|max:50',
            'address'           => 'nullable|string|max:255',
            'city'              => 'nullable|string|max:100',
            'employment_status' => 'nullable|in:employed,self_employed,unemployed',
            'employer'          => 'nullable|string|max:100',
            'monthly_income'    => 'nullable|numeric|min:0',
        ]);

        $borrower->update($validated);

        return response()->json($borrower->load('nextOfKin'));
    }

    public function destroy(Request $request, Borrower $borrower)
    {
        $this->authorizeCompany($request, $borrower->company_id);

        if ($borrower->loans()->whereIn('status', ['active', 'disbursed'])->exists()) {
            return response()->json(
                ['message' => 'Cannot delete a borrower with active loans.'], 422
            );
        }

        $borrower->delete();

        return response()->json(['message' => 'Borrower deleted.']);
    }

    private function authorizeCompany(Request $request, int $companyId): void
    {
        if ($request->user()->company_id !== $companyId && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}

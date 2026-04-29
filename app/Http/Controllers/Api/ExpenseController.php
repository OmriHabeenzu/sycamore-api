<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::where('company_id', $request->user()->company_id)
            ->with('createdBy:id,name')
            ->orderByDesc('expense_date');

        if ($cat = $request->category) {
            $query->where('category', $cat);
        }

        if ($from = $request->date_from) {
            $query->where('expense_date', '>=', $from);
        }

        if ($to = $request->date_to) {
            $query->where('expense_date', '<=', $to);
        }

        return response()->json($query->paginate(25));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'     => 'required|string|max:100',
            'description'  => 'required|string|max:255',
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $validated['created_by'] = $request->user()->id;

        $expense = Expense::create($validated);

        return response()->json($expense->load('createdBy:id,name'), 201);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorise($request, $expense);

        $validated = $request->validate([
            'category'     => 'sometimes|string|max:100',
            'description'  => 'sometimes|string|max:255',
            'amount'       => 'sometimes|numeric|min:0.01',
            'expense_date' => 'sometimes|date',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string',
        ]);

        $expense->update($validated);

        return response()->json($expense->fresh());
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->authorise($request, $expense);
        $expense->delete();

        return response()->json(['message' => 'Expense deleted.']);
    }

    private function authorise(Request $request, Expense $expense): void
    {
        if ($expense->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }
}

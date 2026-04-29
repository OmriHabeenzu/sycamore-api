<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingsController extends Controller
{
    // GET /savings
    public function index(Request $request)
    {
        $query = SavingsAccount::where('company_id', $request->user()->company_id)
            ->with('borrower:id,first_name,last_name,borrower_no')
            ->orderByDesc('created_at');

        if ($s = $request->search) {
            $query->whereHas('borrower', fn ($q) => $q
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name',  'like', "%{$s}%")
                ->orWhere('borrower_no','like', "%{$s}%")
            )->orWhere('account_no', 'like', "%{$s}%");
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    // GET /savings/{account}
    public function show(Request $request, SavingsAccount $savingsAccount)
    {
        $this->authorise($request, $savingsAccount);

        return response()->json(
            $savingsAccount->load([
                'borrower:id,first_name,last_name,borrower_no,phone',
                'transactions',
            ])
        );
    }

    // POST /savings
    public function store(Request $request)
    {
        $validated = $request->validate([
            'borrower_id'  => 'required|exists:borrowers,id',
            'product_name' => 'nullable|string|max:100',
            'interest_rate'=> 'nullable|numeric|min:0|max:100',
            'opened_at'    => 'required|date',
            'notes'        => 'nullable|string',
        ]);

        $validated['company_id']  = $request->user()->company_id;
        $validated['account_no']  = $this->generateAccountNo($request->user()->company_id);
        $validated['balance']     = 0;
        $validated['status']      = 'active';

        $account = SavingsAccount::create($validated);

        return response()->json($account->load('borrower:id,first_name,last_name,borrower_no'), 201);
    }

    // PUT /savings/{account}
    public function update(Request $request, SavingsAccount $savingsAccount)
    {
        $this->authorise($request, $savingsAccount);

        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:100',
            'interest_rate'=> 'sometimes|numeric|min:0|max:100',
            'status'       => 'sometimes|in:active,frozen,closed',
            'notes'        => 'nullable|string',
        ]);

        if (($validated['status'] ?? null) === 'closed' && !$savingsAccount->closed_at) {
            $validated['closed_at'] = now()->toDateString();
        }

        $savingsAccount->update($validated);

        return response()->json($savingsAccount->fresh());
    }

    // POST /savings/{account}/deposit
    public function deposit(Request $request, SavingsAccount $savingsAccount)
    {
        $this->authorise($request, $savingsAccount);

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'reference'        => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'transaction_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($validated, $savingsAccount, $request) {
            $newBalance = $savingsAccount->balance + $validated['amount'];

            $tx = $savingsAccount->transactions()->create([
                'type'             => 'deposit',
                'amount'           => $validated['amount'],
                'balance_after'    => $newBalance,
                'reference'        => $validated['reference'] ?? null,
                'notes'            => $validated['notes'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'created_by'       => $request->user()->id,
            ]);

            $savingsAccount->update(['balance' => $newBalance]);

            return response()->json($tx, 201);
        });
    }

    // POST /savings/{account}/post-interest
    public function postInterest(Request $request, SavingsAccount $savingsAccount)
    {
        $this->authorise($request, $savingsAccount);

        if ($savingsAccount->status !== 'active') {
            return response()->json(['message' => 'Account is not active.'], 422);
        }

        if ($savingsAccount->interest_rate <= 0) {
            return response()->json(['message' => 'No interest rate configured.'], 422);
        }

        $today = $request->input('transaction_date', now()->toDateString());

        $monthlyInterest = round(
            (float) $savingsAccount->balance * ((float) $savingsAccount->interest_rate / 12 / 100),
            2
        );

        if ($monthlyInterest <= 0) {
            return response()->json(['message' => 'Balance too low to generate interest.'], 422);
        }

        $newBalance = round((float) $savingsAccount->balance + $monthlyInterest, 2);

        $tx = $savingsAccount->transactions()->create([
            'type'             => 'interest',
            'amount'           => $monthlyInterest,
            'balance_after'    => $newBalance,
            'notes'            => 'Manual interest posting @ ' . $savingsAccount->interest_rate . '% p.a.',
            'transaction_date' => $today,
            'created_by'       => $request->user()->id,
        ]);

        $savingsAccount->update(['balance' => $newBalance]);

        return response()->json($tx, 201);
    }

    // POST /savings/{account}/withdraw
    public function withdraw(Request $request, SavingsAccount $savingsAccount)
    {
        $this->authorise($request, $savingsAccount);

        $validated = $request->validate([
            'amount'           => 'required|numeric|min:0.01',
            'reference'        => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
            'transaction_date' => 'required|date',
        ]);

        if ($validated['amount'] > $savingsAccount->balance) {
            return response()->json(['message' => 'Insufficient balance.'], 422);
        }

        return DB::transaction(function () use ($validated, $savingsAccount, $request) {
            $newBalance = $savingsAccount->balance - $validated['amount'];

            $tx = $savingsAccount->transactions()->create([
                'type'             => 'withdrawal',
                'amount'           => $validated['amount'],
                'balance_after'    => $newBalance,
                'reference'        => $validated['reference'] ?? null,
                'notes'            => $validated['notes'] ?? null,
                'transaction_date' => $validated['transaction_date'],
                'created_by'       => $request->user()->id,
            ]);

            $savingsAccount->update(['balance' => $newBalance]);

            return response()->json($tx, 201);
        });
    }

    private function authorise(Request $request, SavingsAccount $account): void
    {
        if ($account->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }

    private function generateAccountNo(int $companyId): string
    {
        $last = SavingsAccount::where('company_id', $companyId)->lockForUpdate()->count();
        return 'SAV-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}

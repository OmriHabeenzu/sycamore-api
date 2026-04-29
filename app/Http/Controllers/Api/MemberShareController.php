<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberShareController extends Controller
{
    // GET /members/{borrower}/shares
    public function show(Request $request, $borrowerId)
    {
        $companyId = $request->user()->company_id;

        $share = MemberShare::where('company_id', $companyId)
            ->where('borrower_id', $borrowerId)
            ->with(['borrower:id,first_name,last_name,borrower_no', 'transactions'])
            ->first();

        return response()->json($share);
    }

    // POST /members/{borrower}/shares  — open or update share account
    public function store(Request $request, $borrowerId)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'amount_per_share' => 'required|numeric|min:0.01',
            'joined_date'      => 'required|date',
            'notes'            => 'nullable|string',
        ]);

        $share = MemberShare::firstOrCreate(
            ['company_id' => $companyId, 'borrower_id' => $borrowerId],
            array_merge($validated, ['shares' => 0, 'total_paid' => 0])
        );

        if (!$share->wasRecentlyCreated) {
            $share->update($validated);
        }

        return response()->json($share->load('transactions'), $share->wasRecentlyCreated ? 201 : 200);
    }

    // POST /members/{borrower}/shares/purchase
    public function purchase(Request $request, $borrowerId)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'shares'           => 'required|numeric|min:0.01',
            'amount'           => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference'        => 'nullable|string|max:100',
            'notes'            => 'nullable|string',
        ]);

        $share = MemberShare::where('company_id', $companyId)
            ->where('borrower_id', $borrowerId)
            ->firstOrFail();

        return DB::transaction(function () use ($share, $validated, $request) {
            $share->increment('shares', $validated['shares']);
            $share->increment('total_paid', $validated['amount']);

            $tx = $share->transactions()->create([
                'type'             => 'purchase',
                'shares'           => $validated['shares'],
                'amount'           => $validated['amount'],
                'transaction_date' => $validated['transaction_date'],
                'reference'        => $validated['reference'] ?? null,
                'notes'            => $validated['notes'] ?? null,
                'created_by'       => $request->user()->id,
            ]);

            return response()->json($tx, 201);
        });
    }

    // GET /shares/summary  — total share capital for company
    public function summary(Request $request)
    {
        $companyId = $request->user()->company_id;

        $shares = MemberShare::where('company_id', $companyId)
            ->where('status', 'active')
            ->with('borrower:id,first_name,last_name,borrower_no')
            ->get()
            ->map(fn($s) => [
                'id'             => $s->id,
                'borrower_id'    => $s->borrower_id,
                'member'         => $s->borrower->first_name . ' ' . $s->borrower->last_name,
                'member_no'      => $s->borrower->borrower_no,
                'shares'         => $s->shares,
                'amount_per_share'=> $s->amount_per_share,
                'share_value'    => $s->share_value,
                'total_paid'     => $s->total_paid,
                'joined_date'    => $s->joined_date,
                'status'         => $s->status,
            ]);

        return response()->json([
            'data'              => $shares,
            'total_members'     => $shares->count(),
            'total_shares'      => round($shares->sum('shares'), 2),
            'total_share_capital'=> round($shares->sum('share_value'), 2),
        ]);
    }
}

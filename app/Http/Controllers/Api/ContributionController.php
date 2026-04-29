<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Models\ContributionSchedule;
use Illuminate\Http\Request;

class ContributionController extends Controller
{
    // GET /contributions  — company-wide list with filters
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = Contribution::where('company_id', $companyId)
            ->with('borrower:id,first_name,last_name,borrower_no')
            ->orderByDesc('contribution_date');

        if ($request->borrower_id) $query->where('borrower_id', $request->borrower_id);
        if ($request->date_from)   $query->where('contribution_date', '>=', $request->date_from);
        if ($request->date_to)     $query->where('contribution_date', '<=', $request->date_to);

        return response()->json($query->paginate(50));
    }

    // GET /members/{borrower}/contributions
    public function memberIndex(Request $request, $borrowerId)
    {
        $companyId = $request->user()->company_id;

        $contributions = Contribution::where('company_id', $companyId)
            ->where('borrower_id', $borrowerId)
            ->orderByDesc('contribution_date')
            ->get();

        $schedule = ContributionSchedule::where('company_id', $companyId)
            ->where('borrower_id', $borrowerId)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'contributions' => $contributions,
            'schedule'      => $schedule,
            'total'         => $contributions->sum('amount'),
        ]);
    }

    // POST /contributions
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'borrower_id'               => 'required|exists:borrowers,id',
            'amount'                    => 'required|numeric|min:0.01',
            'contribution_date'         => 'required|date',
            'reference'                 => 'nullable|string|max:100',
            'notes'                     => 'nullable|string',
            'contribution_schedule_id'  => 'nullable|exists:contribution_schedules,id',
        ]);

        $validated['company_id']  = $companyId;
        $validated['received_by'] = $request->user()->id;

        $contribution = Contribution::create($validated);

        return response()->json($contribution->load('borrower:id,first_name,last_name,borrower_no'), 201);
    }

    // DELETE /contributions/{contribution}
    public function destroy(Request $request, Contribution $contribution)
    {
        if ($contribution->company_id !== $request->user()->company_id) abort(403);
        $contribution->delete();
        return response()->json(null, 204);
    }

    // GET /contribution-schedules/{borrower}
    public function getSchedule(Request $request, $borrowerId)
    {
        $companyId = $request->user()->company_id;

        $schedule = ContributionSchedule::where('company_id', $companyId)
            ->where('borrower_id', $borrowerId)
            ->first();

        return response()->json($schedule);
    }

    // POST /contribution-schedules
    public function storeSchedule(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'borrower_id'     => 'required|exists:borrowers,id',
            'expected_amount' => 'required|numeric|min:0.01',
            'frequency'       => 'required|in:weekly,biweekly,monthly',
            'start_date'      => 'required|date',
            'end_date'        => 'nullable|date|after:start_date',
        ]);

        $validated['company_id'] = $companyId;

        // Deactivate any existing schedule for this member
        ContributionSchedule::where('company_id', $companyId)
            ->where('borrower_id', $validated['borrower_id'])
            ->update(['is_active' => false]);

        $schedule = ContributionSchedule::create($validated);

        return response()->json($schedule, 201);
    }
}

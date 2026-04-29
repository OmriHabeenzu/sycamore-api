<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index(Request $request)
    {
        $query = Group::where('company_id', $request->user()->company_id)
            ->with('loanOfficer:id,name')
            ->withCount('members')
            ->orderByDesc('created_at');

        if ($s = $request->search) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('group_no', 'like', "%{$s}%")
            );
        }

        return response()->json($query->paginate(20));
    }

    public function show(Request $request, Group $group)
    {
        $this->authorise($request, $group);

        return response()->json(
            $group->load([
                'loanOfficer:id,name',
                'members:id,first_name,last_name,borrower_no,phone',
                'groupLoans' => fn ($q) => $q->with('loanProduct:id,name')->orderByDesc('created_at'),
            ])
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:100',
            'loan_officer_id'   => 'nullable|exists:users,id',
            'meeting_frequency' => 'nullable|in:weekly,biweekly,monthly',
            'meeting_day'       => 'nullable|string|max:20',
            'meeting_location'  => 'nullable|string|max:255',
        ]);

        $validated['company_id'] = $request->user()->company_id;
        $validated['group_no']   = $this->generateGroupNo($request->user()->company_id);

        $group = Group::create($validated);

        return response()->json($group->load('loanOfficer:id,name'), 201);
    }

    public function update(Request $request, Group $group)
    {
        $this->authorise($request, $group);

        $validated = $request->validate([
            'name'              => 'sometimes|string|max:100',
            'loan_officer_id'   => 'nullable|exists:users,id',
            'meeting_frequency' => 'nullable|in:weekly,biweekly,monthly',
            'meeting_day'       => 'nullable|string|max:20',
            'meeting_location'  => 'nullable|string|max:255',
            'is_active'         => 'sometimes|boolean',
        ]);

        $group->update($validated);

        return response()->json($group->fresh());
    }

    // POST /groups/{group}/members
    public function addMember(Request $request, Group $group)
    {
        $this->authorise($request, $group);

        $validated = $request->validate([
            'borrower_id' => 'required|exists:borrowers,id',
            'role'        => 'nullable|in:member,leader,secretary',
            'joined_at'   => 'required|date',
        ]);

        // Avoid duplicates
        if ($group->members()->where('borrower_id', $validated['borrower_id'])->exists()) {
            return response()->json(['message' => 'Borrower is already a member.'], 422);
        }

        $group->members()->attach($validated['borrower_id'], [
            'role'      => $validated['role'] ?? 'member',
            'joined_at' => $validated['joined_at'],
        ]);

        return response()->json(['message' => 'Member added.'], 201);
    }

    // DELETE /groups/{group}/members/{borrower}
    public function removeMember(Request $request, Group $group, $borrowerId)
    {
        $this->authorise($request, $group);
        $group->members()->detach($borrowerId);

        return response()->json(['message' => 'Member removed.']);
    }

    private function authorise(Request $request, Group $group): void
    {
        if ($group->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }
    }

    private function generateGroupNo(int $companyId): string
    {
        $count = Group::where('company_id', $companyId)->count();
        return 'GRP-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}

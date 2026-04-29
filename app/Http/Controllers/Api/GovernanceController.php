<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GovernanceController extends Controller
{
    public function boardIndex(Request $request)
    {
        $rows = DB::table('board_members')
            ->where('board_members.company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->join('borrowers', 'borrowers.id', '=', 'board_members.borrower_id')
            ->select('board_members.*', 'borrowers.first_name', 'borrowers.last_name', 'borrowers.borrower_no')
            ->orderByRaw("FIELD(role, 'chairman','vice_chairman','treasurer','secretary','committee_member')")
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'role'           => $r->role,
                'appointed_date' => $r->appointed_date,
                'notes'          => $r->notes,
                'borrower' => [
                    'id'          => $r->borrower_id,
                    'first_name'  => $r->first_name,
                    'last_name'   => $r->last_name,
                    'borrower_no' => $r->borrower_no,
                ],
            ]);

        return response()->json($rows);
    }

    public function boardStore(Request $request)
    {
        $request->validate([
            'borrower_id'    => 'required|exists:borrowers,id',
            'role'           => 'required|in:chairman,vice_chairman,treasurer,secretary,committee_member',
            'appointed_date' => 'required|date',
            'notes'          => 'nullable|string',
        ]);

        $companyId = $request->user()->company_id;

        // If adding a unique role (chairman/treasurer etc.), deactivate previous holder
        $uniqueRoles = ['chairman', 'vice_chairman', 'treasurer', 'secretary'];
        if (in_array($request->role, $uniqueRoles)) {
            DB::table('board_members')
                ->where('company_id', $companyId)
                ->where('role', $request->role)
                ->where('is_active', true)
                ->update(['is_active' => false, 'end_date' => now()->toDateString()]);
        }

        $id = DB::table('board_members')->insertGetId([
            'company_id'     => $companyId,
            'borrower_id'    => $request->borrower_id,
            'role'           => $request->role,
            'appointed_date' => $request->appointed_date,
            'notes'          => $request->notes,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function boardDestroy(Request $request, $id)
    {
        DB::table('board_members')
            ->where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->update(['is_active' => false, 'end_date' => now()->toDateString()]);

        return response()->json(null, 204);
    }

    public function minutesIndex(Request $request)
    {
        $rows = DB::table('meeting_minutes')
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('meeting_date')
            ->get();

        return response()->json($rows);
    }

    public function minutesStore(Request $request)
    {
        $request->validate([
            'meeting_date'   => 'required|date',
            'meeting_type'   => 'required|in:general,agm,special,board',
            'agenda'         => 'required|string',
            'minutes'        => 'nullable|string',
            'attendees_count'=> 'nullable|integer|min:0',
        ]);

        $id = DB::table('meeting_minutes')->insertGetId([
            'company_id'      => $request->user()->company_id,
            'meeting_date'    => $request->meeting_date,
            'meeting_type'    => $request->meeting_type,
            'agenda'          => $request->agenda,
            'minutes'         => $request->minutes,
            'attendees_count' => $request->attendees_count,
            'recorded_by'     => $request->user()->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function minutesDestroy(Request $request, $id)
    {
        DB::table('meeting_minutes')
            ->where('id', $id)
            ->where('company_id', $request->user()->company_id)
            ->delete();

        return response()->json(null, 204);
    }
}

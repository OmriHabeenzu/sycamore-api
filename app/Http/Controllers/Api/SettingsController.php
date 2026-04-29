<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    // --- Company profile ---

    public function getCompany(Request $request)
    {
        return response()->json($request->user()->company);
    }

    public function updateCompany(Request $request)
    {
        $company = $request->user()->company;

        $validated = $request->validate([
            'name'          => 'required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:100',
            'address'       => 'nullable|string|max:255',
            'city'          => 'nullable|string|max:100',
            'primary_color' => 'nullable|string|max:7',
        ]);

        $company->update($validated);

        return response()->json($company->fresh());
    }

    // --- Users / Staff ---

    public function getUsers(Request $request)
    {
        $users = User::where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    public function storeUser(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'role'     => 'required|in:admin,manager,loan_officer,cashier',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'company_id' => $companyId,
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'phone'      => $validated['phone'] ?? null,
            'role'       => $validated['role'],
            'password'   => Hash::make($validated['password']),
            'is_active'  => true,
        ]);

        return response()->json($user, 201);
    }

    public function updateUser(Request $request, User $user)
    {
        if ($user->company_id !== $request->user()->company_id) abort(403);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:100',
            'phone'     => 'nullable|string|max:20',
            'role'      => 'sometimes|in:admin,manager,loan_officer,cashier',
            'is_active' => 'boolean',
            'email'     => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json($user->fresh());
    }

    public function resetPassword(Request $request, User $user)
    {
        if ($user->company_id !== $request->user()->company_id) abort(403);

        $request->validate(['password' => 'required|string|min:6']);

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password updated.']);
    }

    // --- SMS Logs ---

    public function smsLogs(Request $request)
    {
        $logs = SmsLog::where('company_id', $request->user()->company_id)
            ->with('borrower:id,first_name,last_name')
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($logs);
    }
}

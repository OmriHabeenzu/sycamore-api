<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!$request->user()?->isSuperAdmin()) abort(403);
            return $next($request);
        });
    }

    public function tenants(Request $request)
    {
        $tenants = Company::withCount(['loans', 'borrowers', 'users'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tenants);
    }

    public function storeTenant(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:100',
            'email'             => 'nullable|email',
            'phone'             => 'nullable|string|max:20',
            'country'           => 'nullable|string|max:50',
            'subscription_plan' => 'required|in:basic,pro,enterprise',
            // Admin user for this tenant
            'admin_name'        => 'required|string|max:100',
            'admin_email'       => 'required|email|unique:users,email',
            'admin_password'    => 'required|string|min:6',
        ]);

        $company = Company::create([
            'name'              => $validated['name'],
            'slug'              => Str::slug($validated['name']) . '-' . Str::random(4),
            'email'             => $validated['email'] ?? null,
            'phone'             => $validated['phone'] ?? null,
            'country'           => $validated['country'] ?? 'Zambia',
            'status'            => 'active',
            'subscription_plan' => $validated['subscription_plan'],
        ]);

        User::create([
            'company_id' => $company->id,
            'name'       => $validated['admin_name'],
            'email'      => $validated['admin_email'],
            'password'   => Hash::make($validated['admin_password']),
            'role'       => 'admin',
            'is_active'  => true,
        ]);

        return response()->json($company->load('users'), 201);
    }

    public function updateTenant(Request $request, Company $company)
    {
        $validated = $request->validate([
            'name'                    => 'sometimes|string|max:100',
            'status'                  => 'sometimes|in:trial,active,suspended',
            'subscription_plan'       => 'sometimes|in:basic,pro,enterprise',
            'subscription_expires_at' => 'nullable|date',
        ]);

        $company->update($validated);

        return response()->json($company->fresh());
    }

    public function suspendTenant(Request $request, Company $company)
    {
        $company->update(['status' => 'suspended']);
        return response()->json(['message' => 'Tenant suspended.']);
    }

    public function activateTenant(Request $request, Company $company)
    {
        $company->update(['status' => 'active']);
        return response()->json(['message' => 'Tenant activated.']);
    }

    public function overview()
    {
        return response()->json([
            'total_tenants'   => Company::count(),
            'active_tenants'  => Company::where('status', 'active')->count(),
            'suspended'       => Company::where('status', 'suspended')->count(),
            'total_loans'     => \App\Models\Loan::count(),
            'total_borrowers' => \App\Models\Borrower::count(),
            'total_collected' => \App\Models\Repayment::sum('amount'),
        ]);
    }
}

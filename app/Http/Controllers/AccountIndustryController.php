<?php

namespace App\Http\Controllers;

use App\Models\AccountIndustry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountIndustryController extends Controller
{
    public function index(Request $request)
    {
        $query = AccountIndustry::with('creator')
            ->where('created_by', createdBy());
            
            if (auth()->user()->type !== 'company' && !auth()->user()->can('manage-account-industries')) {
    $query->whereRaw('1 = 0');
}

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->sort_field && $request->sort_direction) {
            $query->orderBy($request->sort_field, $request->sort_direction);
        } else {
            $query->orderBy('id', 'desc');
        }

        $accountIndustries = $query->paginate($request->per_page ?? 10);

        return Inertia::render('account-industries/index', [
            'accountIndustries' => $accountIndustries,
            'filters' => $request->only(['search', 'status', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive'
        ]);

        AccountIndustry::create([
            ...$request->all(),
            'created_by' => createdBy()
        ]);

        return redirect()->back()->with('success', __('Account industry created successfully'));
    }

    public function update(Request $request, AccountIndustry $accountIndustry)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive'
        ]);

        $accountIndustry->update($request->all());

        return redirect()->back()->with('success', __('Account industry updated successfully'));
    }

    public function destroy(AccountIndustry $accountIndustry)
    {
        if ($accountIndustry->accounts()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete account industry that has associated accounts'));
        }

        $accountIndustry->delete();

        return redirect()->back()->with('success', __('Account industry deleted successfully'));
    }

    public function toggleStatus(AccountIndustry $accountIndustry)
    {
        $accountIndustry->update([
            'status' => $accountIndustry->status === 'active' ? 'inactive' : 'active'
        ]);

        return redirect()->back()->with('success', __('Account industry status updated successfully'));
    }
}
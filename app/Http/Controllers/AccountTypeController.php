<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = AccountType::with('creator')
            ->where('created_by', createdBy());
            
            if (auth()->user()->type !== 'company' && !auth()->user()->can('manage-account-types')) {
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
            $query->latest();
        }

        $accountTypes = $query->paginate($request->per_page ?? 10);

        return Inertia::render('account-types/index', [
            'accountTypes' => $accountTypes,
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

        AccountType::create([
            ...$request->all(),
            'created_by' => createdBy()
        ]);

        return redirect()->back()->with('success', __('Account type created successfully.'));
    }

    public function update(Request $request, AccountType $accountType)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'status' => 'required|in:active,inactive'
        ]);

        $accountType->update($request->all());

        return redirect()->back()->with('success', __('Account type updated successfully.'));
    }

    public function destroy(AccountType $accountType)
    {
        if ($accountType->accounts()->count() > 0) {
            return redirect()->back()->with('error', __('Cannot delete account type that has associated accounts.'));
        }

        $accountType->delete();

        return redirect()->back()->with('success', __('Account type deleted successfully.'));
    }

    public function toggleStatus(AccountType $accountType)
    {
        $accountType->update([
            'status' => $accountType->status === 'active' ? 'inactive' : 'active'
        ]);

        return redirect()->back()->with('success', __('Account type status updated successfully.'));
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class DocumentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DocumentType::with(['creator']);

        // Apply search filter
        if ($request->filled('search')) {
            $query->where('type_name', 'like', '%' . $request->search . '%');
        }

        // Apply status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Apply sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('id', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Apply authorization filter
        $query->where('created_by', createdBy());

        $documentTypes = $query->paginate($request->get('per_page', 10));

        return Inertia::render('document-types/index', [
            'documentTypes' => $documentTypes,
            'filters' => $request->only(['search', 'status', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
    //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type_name' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        DocumentType::create([
            'type_name' => $request->type_name,
            'status' => $request->status,
            'created_by' => createdBy(),
        ]);

        return redirect()->route('document-types.index')
            ->with('success', __('Document type created successfully.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentType $documentType)
    {
        // Check if user has access to this document type
        if ($documentType->created_by !== createdBy()) {
            abort(403, 'Unauthorized access.');
        }

        $documentType->load(['creator']);

        return Inertia::render('document-types/show', [
            'documentType' => $documentType
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DocumentType $documentType)
    {
    //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentType $documentType)
    {
        // Check if user has access to this document type
        if ($documentType->created_by !== createdBy()) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'type_name' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        if ($request->status === 'inactive' && $documentType->status === 'active') {
            if ($documentType->documents()->exists()) {
                return redirect()->route('document-types.index')
                    ->with('error', __('Cannot deactivate document type that is currently assigned to one or more documents.'));
            }
        }

        $documentType->update([
            'type_name' => $request->type_name,
            'status' => $request->status,
        ]);

        return redirect()->route('document-types.index')
            ->with('success', __('Document type updated successfully.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentType $documentType)
    {
        // Check if user has access to this document type
        if ($documentType->created_by !== createdBy()) {
            abort(403, 'Unauthorized access.');
        }

        if ($documentType->documents()->exists()) {
            return redirect()->route('document-types.index')
                ->with('error', __('Cannot delete document type that is currently assigned to one or more documents.'));
        }

        $documentType->delete();

        return redirect()->route('document-types.index')
            ->with('success', __('Document type deleted successfully.'));
    }

    /**
     * Toggle the status of the specified resource.
     */
    public function toggleStatus(DocumentType $documentType)
    {
        // Check if user has access to this document type
        if ($documentType->created_by !== createdBy()) {
            abort(403, 'Unauthorized access.');
        }

        if ($documentType->status === 'active') {
            if ($documentType->documents()->exists()) {
                return redirect()->route('document-types.index')
                    ->with('error', __('Cannot deactivate document type that is currently assigned to one or more documents.'));
            }
            $newStatus = 'inactive';
        } else {
            $newStatus = 'active';
        }
        $documentType->update(['status' => $newStatus]);

        return redirect()->route('document-types.index')
            ->with('success', __('Document type status updated successfully.'));
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-document-types')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        try {
            if (\App\Models\Document::whereIn('type_id', $validated['ids'])->exists()) {
                return redirect()->back()->with('error', __('Cannot bulk delete types that are currently assigned to one or more documents.'));
            }

            $query = DocumentType::whereIn('id', $validated['ids'])->where('created_by', createdBy());
            $count = $query->count();

            if ($count === 0) {
                return redirect()->back()->with('warning', __('No valid records selected to delete.'));
            }

            $query->delete();
            return redirect()->back()->with('success', __('Successfully deleted :count records.', ['count' => $count]));
        }
        catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete records: :error', ['error' => $e->getMessage()]));
        }
    }
}
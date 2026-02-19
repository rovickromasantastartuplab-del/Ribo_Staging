<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NewsletterController extends Controller
{
    public function index(Request $request)
    {
        $query = Newsletter::query();

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('email', 'like', "%{$search}%");
        }

        $perPage = $request->get('per_page', 15);
        $newsletters = $query->latest()->paginate($perPage);

        return Inertia::render('newsletters/index', [
            'newsletters' => $newsletters,
            'filters' => $request->only(['search', 'per_page']),
        ]);
    }

    public function destroy(Newsletter $newsletter)
    {
        $newsletter->delete();

        return redirect()->back()->with('success', 'Newsletter subscription deleted successfully.');
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Document;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * GET /borrowers/{borrower}/documents
     * GET /loans/{loan}/documents
     */
    public function index(Request $request, $entityType, $entityId)
    {
        $docs = Document::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('company_id', $request->user()->company_id)
            ->with('uploadedBy:id,name')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($docs);
    }

    /**
     * POST /borrowers/{borrower}/documents
     * POST /loans/{loan}/documents
     */
    public function store(Request $request, $entityType, $entityId)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10 MB max
            'name' => 'nullable|string|max:100',
        ]);

        $file      = $request->file('file');
        $name      = $request->input('name') ?: $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $path      = $file->storeAs(
            "documents/{$entityType}/{$entityId}",
            Str::uuid() . '.' . $extension,
            'public'
        );

        $doc = Document::create([
            'company_id'  => $request->user()->company_id,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'name'        => $name,
            'file_path'   => $path,
            'file_type'   => $file->getMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($doc->load('uploadedBy:id,name'), 201);
    }

    /**
     * DELETE /documents/{document}
     */
    public function destroy(Request $request, Document $document)
    {
        if ($document->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }

        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    /**
     * GET /documents/{document}/download
     */
    public function download(Request $request, Document $document)
    {
        if ($document->company_id !== $request->user()->company_id && !$request->user()->isSuperAdmin()) {
            abort(403);
        }

        $path = Storage::disk('public')->path($document->file_path);

        if (!file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->download($path, $document->name);
    }
}

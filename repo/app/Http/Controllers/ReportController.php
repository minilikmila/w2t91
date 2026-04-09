<?php

namespace App\Http\Controllers;

use App\Models\ReportDefinition;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = ReportDefinition::with('creator');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'required|string|in:learners,enrollments,bookings,audit',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'columns.*' => 'string',
            'output_format' => 'nullable|string|in:csv,json',
            'metadata' => 'nullable|array',
        ]);

        $report = ReportDefinition::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'filters' => $request->filters,
            'columns' => $request->columns,
            'output_format' => $request->output_format ?? 'csv',
            'created_by' => $request->user()->id,
            'metadata' => $request->metadata,
        ]);

        return response()->json([
            'message' => 'Report definition created.',
            'data' => $report,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $report = ReportDefinition::with('creator')->findOrFail($id);

        return response()->json(['data' => $report]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'type' => 'sometimes|required|string|in:learners,enrollments,bookings,audit',
            'filters' => 'nullable|array',
            'columns' => 'nullable|array',
            'columns.*' => 'string',
            'output_format' => 'nullable|string|in:csv,json',
            'metadata' => 'nullable|array',
        ]);

        $report = ReportDefinition::findOrFail($id);
        $report->update($request->only([
            'name', 'description', 'type', 'filters',
            'columns', 'output_format', 'metadata',
        ]));

        return response()->json([
            'message' => 'Report definition updated.',
            'data' => $report->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $report = ReportDefinition::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report definition deleted.']);
    }

    public function generate(int $id): JsonResponse
    {
        $report = ReportDefinition::findOrFail($id);

        $result = $this->reportService->generate($report);

        return response()->json([
            'message' => 'Report generated successfully.',
            'data' => $result,
        ]);
    }

    public function download(int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $report = ReportDefinition::findOrFail($id);

        $content = $this->reportService->getExportContent($report);

        if ($content === null) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'No generated export found for this report. Generate it first.',
            ], 404);
        }

        $format = $report->output_format ?? 'csv';
        $contentType = $format === 'json' ? 'application/json' : 'text/csv';
        $filename = basename($report->last_export_path);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => $contentType,
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\ReportDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ReportService
{
    private const EXPORT_DIRECTORY = 'reports';

    /**
     * Generate an export for a report definition.
     */
    public function generate(ReportDefinition $report): array
    {
        $data = $this->fetchData($report);
        $filteredData = $this->applyColumns($data, $report->columns);

        $format = $report->output_format ?? 'csv';
        $filename = $this->buildFilename($report, $format);
        $path = self::EXPORT_DIRECTORY . '/' . $filename;

        if ($format === 'json') {
            $content = json_encode($filteredData->toArray(), JSON_PRETTY_PRINT);
        } else {
            $content = $this->toCsv($filteredData);
        }

        Storage::disk('local')->put($path, $content);

        $report->update([
            'last_export_path' => $path,
            'last_generated_at' => now(),
        ]);

        return [
            'report_id' => $report->id,
            'format' => $format,
            'path' => $path,
            'row_count' => $filteredData->count(),
            'generated_at' => $report->last_generated_at->toIso8601String(),
        ];
    }

    /**
     * Retrieve the content of a generated export.
     */
    public function getExportContent(ReportDefinition $report): ?string
    {
        if (!$report->last_export_path) {
            return null;
        }

        if (!Storage::disk('local')->exists($report->last_export_path)) {
            return null;
        }

        return Storage::disk('local')->get($report->last_export_path);
    }

    /**
     * Fetch data based on report type and filters.
     */
    private function fetchData(ReportDefinition $report): Collection
    {
        $filters = $report->filters ?? [];

        return match ($report->type) {
            'learners' => $this->fetchLearners($filters),
            'enrollments' => $this->fetchEnrollments($filters),
            'bookings' => $this->fetchBookings($filters),
            'audit' => $this->fetchAuditEvents($filters),
            default => collect(),
        };
    }

    private function fetchLearners(array $filters): Collection
    {
        $query = Learner::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['nationality'])) {
            $query->where('nationality', $filters['nationality']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->get()->map(fn ($l) => $l->toArray());
    }

    private function fetchEnrollments(array $filters): Collection
    {
        $query = Enrollment::with('learner');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['program_name'])) {
            $query->where('program_name', $filters['program_name']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->get()->map(function ($e) {
            $data = $e->toArray();
            $data['learner_name'] = $e->learner ? $e->learner->full_name : null;
            return $data;
        });
    }

    private function fetchBookings(array $filters): Collection
    {
        $query = Booking::with(['resource', 'learner']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['resource_id'])) {
            $query->where('resource_id', $filters['resource_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('start_time', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('start_time', '<=', $filters['to']);
        }

        return $query->get()->map(function ($b) {
            $data = $b->toArray();
            $data['resource_name'] = $b->resource?->name;
            $data['learner_name'] = $b->learner?->full_name;
            return $data;
        });
    }

    private function fetchAuditEvents(array $filters): Collection
    {
        $query = AuditEvent::query();

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('entity_type', $filters['entity_type']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return $query->get()->map(fn ($e) => $e->toArray());
    }

    /**
     * Filter data to only include specified columns.
     */
    private function applyColumns(Collection $data, ?array $columns): Collection
    {
        if (empty($columns)) {
            return $data;
        }

        return $data->map(function ($row) use ($columns) {
            $filtered = [];
            foreach ($columns as $col) {
                $filtered[$col] = $row[$col] ?? null;
            }
            return $filtered;
        });
    }

    /**
     * Convert collection to CSV string.
     */
    private function toCsv(Collection $data): string
    {
        if ($data->isEmpty()) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Header row
        $firstRow = $data->first();
        fputcsv($output, array_keys($firstRow));

        // Data rows
        foreach ($data as $row) {
            $flatRow = array_map(function ($value) {
                if (is_array($value)) {
                    return json_encode($value);
                }
                return $value;
            }, $row);
            fputcsv($output, $flatRow);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Build a unique filename for the export.
     */
    private function buildFilename(ReportDefinition $report, string $format): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower($report->name));
        $timestamp = now()->format('Ymd_His');
        return "{$slug}_{$timestamp}.{$format}";
    }
}

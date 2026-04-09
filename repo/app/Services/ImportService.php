<?php

namespace App\Services;

use App\Models\Learner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class ImportService
{
    private const MAX_ROWS = 10000;

    private DataNormalizationService $normalizer;
    private DeduplicationService $deduplication;

    public function __construct(
        DataNormalizationService $normalizer,
        DeduplicationService $deduplication
    ) {
        $this->normalizer = $normalizer;
        $this->deduplication = $deduplication;
    }

    /**
     * Import learners from an uploaded CSV/XLSX file.
     */
    public function importLearners(UploadedFile $file): array
    {
        $rows = $this->parseFile($file);

        if (count($rows) > self::MAX_ROWS) {
            return [
                'success' => false,
                'message' => 'File exceeds maximum of ' . self::MAX_ROWS . ' rows.',
                'total_rows' => count($rows),
                'imported' => 0,
                'failed' => 0,
                'errors' => [],
            ];
        }

        $imported = [];
        $failed = [];
        $duplicates = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2 for 1-based index and header row

            // Normalize the row
            $normalized = $this->normalizer->normalizeLearnerRow($row);

            // Validate the row
            $validation = $this->validateRow($normalized);

            if ($validation->fails()) {
                $failed[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'errors' => $validation->errors()->toArray(),
                ];
                continue;
            }

            // Check for duplicates before import
            $candidates = $this->deduplication->findDuplicateCandidates($normalized);

            // Create the learner
            $learnerData = $this->mapRowToLearner($normalized);
            $learner = Learner::create($learnerData);

            // Process deduplication
            $dedupResult = $this->deduplication->processLearner($learner);

            $importedEntry = [
                'row' => $rowNumber,
                'learner_id' => $learner->id,
            ];

            if ($dedupResult['duplicate_count'] > 0 || !empty($candidates)) {
                $importedEntry['duplicate_candidates'] = count($candidates);
                $duplicates[] = [
                    'row' => $rowNumber,
                    'learner_id' => $learner->id,
                    'candidate_count' => max($dedupResult['duplicate_count'], count($candidates)),
                ];
            }

            $imported[] = $importedEntry;
        }

        return [
            'success' => true,
            'message' => 'Import completed.',
            'total_rows' => count($rows),
            'imported' => count($imported),
            'failed' => count($failed),
            'duplicate_flags' => count($duplicates),
            'imported_records' => $imported,
            'failed_records' => $failed,
            'duplicate_records' => $duplicates,
        ];
    }

    /**
     * Parse a CSV or XLSX file into an array of associative arrays.
     */
    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsv($file);
        }

        return $this->parseSpreadsheet($file);
    }

    /**
     * Parse a CSV file.
     */
    private function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        $headers = null;

        while (($data = fgetcsv($handle)) !== false) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim($h)), $data);
                continue;
            }

            if (count($data) !== count($headers)) {
                // Pad or trim to match header count
                $data = array_pad($data, count($headers), '');
                $data = array_slice($data, 0, count($headers));
            }

            $row = array_combine($headers, $data);
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse an XLSX/XLS file using maatwebsite/excel or basic XML parsing.
     */
    private function parseSpreadsheet(UploadedFile $file): array
    {
        // Use PhpSpreadsheet via maatwebsite/excel if available
        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return $this->parseWithPhpSpreadsheet($file);
        }

        // Fallback: try to read as CSV (user may have mis-named the file)
        return $this->parseCsv($file);
    }

    /**
     * Parse using PhpSpreadsheet directly.
     */
    private function parseWithPhpSpreadsheet(UploadedFile $file): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $headers = null;

        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $data = [];
            foreach ($cellIterator as $cell) {
                $data[] = $cell->getValue();
            }

            if ($headers === null) {
                $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $data);
                continue;
            }

            if (count($data) !== count($headers)) {
                $data = array_pad($data, count($headers), '');
                $data = array_slice($data, 0, count($headers));
            }

            $row = array_combine($headers, array_map(fn ($v) => (string) ($v ?? ''), $data));

            // Skip entirely empty rows
            if (implode('', array_values($row)) === '') {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Validate a single import row.
     */
    private function validateRow(array $row): \Illuminate\Validation\Validator
    {
        return Validator::make($row, [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_contact' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Map a normalized row to learner model attributes.
     */
    private function mapRowToLearner(array $row): array
    {
        $fields = [
            'first_name', 'last_name', 'date_of_birth', 'email', 'phone',
            'gender', 'nationality', 'language', 'address',
            'guardian_name', 'guardian_contact',
        ];

        $data = [];
        foreach ($fields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $data[$field] = $row[$field];
            }
        }

        return $data;
    }
}

<?php

namespace App\Services;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\CampusLocation;
use App\Models\InventoryItem;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class InventoryImportService
{
    /**
     * Standard CSV template header & sample data.
     */
    public function generateCsvTemplate(): string
    {
        $headers = [
            'kode_aset',
            'nama',
            'kategori',
            'tipe',
            'status',
            'kondisi',
            'gedung',
            'lantai',
            'lokasi_detail',
            'merk_model',
            'serial_number',
            'siklus_lisensi',
            'tanggal_perbaruan',
            'alamat_akses',
            'penerima_akses',
            'keterangan',
        ];

        $sampleRows = [
            [
                '',
                'PC Workstation Lab RPL 01',
                'Komputer & PC Lab',
                'fisik',
                'in_use',
                'good',
                'Gedung Utama',
                'Lantai 2',
                'Lab Software Engineering PC-01',
                'Dell OptiPlex 7090 i7 16GB',
                'SN-DELL-7090-001',
                '',
                '',
                '',
                '',
                'Inventaris rutin lab praktikum',
            ],
            [
                '',
                'Lisensi Zoom Enterprise PuTI',
                'Digital & Lisensi Software',
                'digital',
                'active',
                'good',
                '',
                '',
                'Cloud License',
                'Zoom Video Communications',
                'LIC-ZOOM-2026-ENT',
                'yearly',
                '2027-01-15',
                'https://telkomuniversity.zoom.us',
                'Seluruh Dosen & Unit PuTI',
                'Paket 500 peserta per host',
            ],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string) $csv;
    }

    /**
     * Parse an uploaded file (.csv or .xlsx) into an array of associative rows.
     *
     * @return array<int, array<string, string>>
     */
    public function parseUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv' || $extension === 'txt') {
            return $this->parseCsvFile($file->getRealPath());
        }

        if ($extension === 'xlsx') {
            return $this->parseXlsxFile($file->getRealPath());
        }

        throw new \InvalidArgumentException('Format file tidak didukung. Harap unggah file .xlsx atau .csv.');
    }

    /**
     * Parse CSV file.
     *
     * @return array<int, array<string, string>>
     */
    protected function parseCsvFile(string $filePath): array
    {
        $rows = [];
        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return [];
        }

        // Detect delimiter (comma or semicolon)
        $firstLine = fgets($handle);
        $delimiter = str_contains($firstLine, ';') ? ';' : ',';
        rewind($handle);

        $header = null;
        while (($data = fgetcsv($handle, 4000, $delimiter)) !== false) {
            if (! array_filter($data)) {
                continue;
            }

            if (! $header) {
                $header = array_map(fn ($col) => strtolower(trim((string) $col)), $data);

                continue;
            }

            $row = [];
            foreach ($header as $index => $colName) {
                $row[$colName] = trim((string) ($data[$index] ?? ''));
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Parse XLSX file using native ZipArchive + SimpleXMLElement (zero external packages).
     *
     * @return array<int, array<string, string>>
     */
    protected function parseXlsxFile(string $filePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Gagal membuka file XLSX.');
        }

        // 1. Read shared strings if present
        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml) {
            $xml = @simplexml_load_string($sharedStringsXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } elseif (isset($si->r)) {
                        $text = '';
                        foreach ($si->r as $r) {
                            $text .= (string) $r->t;
                        }
                        $sharedStrings[] = $text;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // 2. Read sheet1.xml
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            throw new \RuntimeException('Sheet1 tidak ditemukan di dalam file Excel.');
        }

        $xml = @simplexml_load_string($sheetXml);
        if (! $xml || ! isset($xml->sheetData)) {
            return [];
        }

        $rawRows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $cellRef = (string) $cell['r'];
                preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
                $colLetters = $matches[1] ?? 'A';
                $colIndex = $this->columnLettersToIndex($colLetters);

                $cellType = (string) $cell['t'];
                $val = '';
                if ($cellType === 's') {
                    $stringIndex = (int) $cell->v;
                    $val = $sharedStrings[$stringIndex] ?? '';
                } elseif ($cellType === 'inlineStr') {
                    $val = (string) $cell->is->t;
                } else {
                    $val = (string) $cell->v;
                }

                $rowData[$colIndex] = trim($val);
            }

            // Fill empty cells up to max column
            if (! empty($rowData)) {
                $maxCol = max(array_keys($rowData));
                $normalizedRow = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $normalizedRow[$i] = $rowData[$i] ?? '';
                }
                $rawRows[] = $normalizedRow;
            }
        }

        if (empty($rawRows)) {
            return [];
        }

        $headerRow = array_shift($rawRows);
        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $headerRow);

        $parsedRows = [];
        foreach ($rawRows as $row) {
            if (! array_filter($row)) {
                continue;
            }
            $item = [];
            foreach ($header as $idx => $key) {
                if ($key !== '') {
                    $item[$key] = trim((string) ($row[$idx] ?? ''));
                }
            }
            $parsedRows[] = $item;
        }

        return $parsedRows;
    }

    /**
     * Convert Excel column letters like A, B, Z, AA to 0-based integer index.
     */
    protected function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $len = strlen($letters);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    /**
     * Import multiple parsed rows into InventoryItems and record history.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array{imported: int, errors: list<string>}
     */
    public function importRows(array $rows, int $userId): array
    {
        $imported = 0;
        $errors = [];

        $buildings = CampusLocation::where('type', 'building')->get()->keyBy(fn ($b) => strtolower(trim($b->name)));
        $floors = CampusLocation::where('type', 'floor')->get()->keyBy(fn ($f) => strtolower(trim($f->name)));

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2; // account for header line

            $name = $row['nama'] ?? $row['name'] ?? null;
            if (! $name) {
                $errors[] = "Baris {$rowNum}: Kolom nama aset tidak boleh kosong.";

                continue;
            }

            $assetCode = trim((string) ($row['kode_aset'] ?? $row['asset_code'] ?? ''));
            if ($assetCode !== '' && InventoryItem::where('asset_code', $assetCode)->exists()) {
                $errors[] = "Baris {$rowNum}: Kode aset '{$assetCode}' sudah terdaftar di sistem.";

                continue;
            }

            if ($assetCode === '') {
                $assetCode = InventoryItem::generateAssetCode();
            }

            // Asset Type: 'physical' or 'digital'
            $rawType = strtolower(trim((string) ($row['tipe'] ?? $row['type'] ?? $row['asset_type'] ?? 'fisik')));
            $assetType = str_contains($rawType, 'digit') || str_contains($rawType, 'lisensi') ? 'digital' : 'physical';

            // Category
            $category = trim((string) ($row['kategori'] ?? $row['category'] ?? ''));
            if ($category === '') {
                $category = $assetType === 'digital' ? 'Digital & Lisensi Software' : 'Periferal & Lainnya';
            }

            // Status resolution
            $rawStatus = strtolower(trim((string) ($row['status'] ?? 'active')));
            $status = match (true) {
                str_contains($rawStatus, 'pakai') || str_contains($rawStatus, 'guna') || $rawStatus === 'in_use' => InventoryStatus::InUse->value,
                str_contains($rawStatus, 'cadang') || str_contains($rawStatus, 'gudang') || $rawStatus === 'spare' => InventoryStatus::Spare->value,
                str_contains($rawStatus, 'rawat') || str_contains($rawStatus, 'perbaik') || $rawStatus === 'maintenance' => InventoryStatus::Maintenance->value,
                str_contains($rawStatus, 'afkir') || str_contains($rawStatus, 'hapus') || $rawStatus === 'retired' => InventoryStatus::Retired->value,
                default => InventoryStatus::Active->value,
            };

            // Condition resolution
            $rawCondition = strtolower(trim((string) ($row['kondisi'] ?? $row['condition'] ?? 'good')));
            $condition = match (true) {
                str_contains($rawCondition, 'rusak') || $rawCondition === 'damaged' => InventoryCondition::Damaged->value,
                str_contains($rawCondition, 'rawat') || str_contains($rawCondition, 'perbaik') || $rawCondition === 'maintenance' => InventoryCondition::Maintenance->value,
                str_contains($rawCondition, 'afkir') || $rawCondition === 'retired' => InventoryCondition::Retired->value,
                default => InventoryCondition::Good->value,
            };

            // Building and Floor resolution
            $buildingName = strtolower(trim((string) ($row['gedung'] ?? $row['building'] ?? '')));
            $floorName = strtolower(trim((string) ($row['lantai'] ?? $row['floor'] ?? '')));
            $buildingId = $buildings->get($buildingName)?->id;
            $floorId = $floors->get($floorName)?->id;

            // License fields
            $billingCycle = null;
            $rawCycle = strtolower(trim((string) ($row['siklus_lisensi'] ?? $row['billing_cycle'] ?? '')));
            if ($rawCycle !== '') {
                $billingCycle = str_contains($rawCycle, 'tahun') || $rawCycle === 'yearly' ? 'yearly' : 'monthly';
            }

            $renewalDate = null;
            $rawRenewal = trim((string) ($row['tanggal_perbaruan'] ?? $row['renewal_date'] ?? ''));
            if ($rawRenewal !== '') {
                try {
                    $renewalDate = Carbon::parse($rawRenewal)->format('Y-m-d');
                } catch (\Throwable) {
                    $renewalDate = null;
                }
            }

            $item = InventoryItem::create([
                'asset_code' => $assetCode,
                'name' => $name,
                'category' => $category,
                'asset_type' => $assetType,
                'brand_model' => $row['merk_model'] ?? $row['brand_model'] ?? null,
                'serial_number' => $row['serial_number'] ?? null,
                'building_id' => $buildingId,
                'floor_id' => $floorId,
                'campus_location_id' => null,
                'location_detail' => $row['lokasi_detail'] ?? $row['location_detail'] ?? null,
                'condition' => $condition,
                'status' => $status,
                'billing_cycle' => $billingCycle,
                'renewal_date' => $renewalDate,
                'access_url' => $row['alamat_akses'] ?? $row['access_url'] ?? null,
                'access_recipients' => $row['penerima_akses'] ?? $row['access_recipients'] ?? null,
                'notes' => $row['keterangan'] ?? $row['notes'] ?? null,
            ]);

            $item->recordHistory(
                changeType: 'bulk_import',
                oldState: [],
                notes: 'Pendaftaran aset melalui import massal (Excel/CSV).',
                userId: $userId
            );

            $imported++;
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}

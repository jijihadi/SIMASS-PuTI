<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\ServiceCatalog;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class LunaKnowledgeBaseController extends Controller
{
    /**
     * Standard Knowledge Base documents expected by the Luna AI model.
     *
     * @var list<string>
     */
    public const STANDARD_DOCUMENTS = [
        'LUNA_01_Identitas.md',
        'LUNA_02_AlurLayanan.md',
        'LUNA_03_KategoriLayanan.md',
        'LUNA_04_Keamanan.md',
        'LUNA_06_Arsitektur.md',
    ];

    /**
     * List all Knowledge Base documents (with optional full content & live SIMASS sync data).
     */
    public function index(Request $request): JsonResponse
    {
        $kbPath = $this->kbPath();
        $includeContent = $request->boolean('include_content', false);
        $includeSyncData = $request->boolean('include_sync_data', false);

        $documents = [];

        if (File::isDirectory($kbPath)) {
            $files = collect(File::files($kbPath))
                ->filter(fn (\SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.md'))
                ->sortBy(fn (\SplFileInfo $file): string => $file->getFilename())
                ->values();

            foreach ($files as $file) {
                $filename = $file->getFilename();
                $content = File::get($file->getPathname());
                $entry = $this->formatDocumentMeta($filename, $file->getPathname(), $content, $includeContent);
                $documents[] = $entry;
            }
        }

        $payload = [
            'success' => true,
            'kb_path_configured' => $kbPath !== '',
            'standard_documents' => self::STANDARD_DOCUMENTS,
            'count' => count($documents),
            'data' => $documents,
        ];

        if ($includeSyncData) {
            $payload['sync_data'] = [
                'ticket_services' => Ticket::SERVICES,
                'service_catalogs' => ServiceCatalog::where('is_active', true)
                    ->orderBy('category')
                    ->orderBy('name')
                    ->get(['id', 'category', 'name', 'sla_resolution_hours']),
                'inventory_categories' => InventoryCategory::where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name', 'description']),
            ];
        }

        return response()->json($payload);
    }

    /**
     * Get a single Knowledge Base document by filename or key (e.g. LUNA_03_KategoriLayanan or LUNA_03_KategoriLayanan.md).
     */
    public function show(string $document): JsonResponse
    {
        $filename = $this->normalizeDocumentFilename($document);
        if ($filename === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nama dokumen tidak valid. Gunakan format LUNA_XX_NamaDokumen.md (contoh: LUNA_03_KategoriLayanan.md).',
            ], 422);
        }

        $fullPath = $this->kbPath().DIRECTORY_SEPARATOR.$filename;
        if (! File::exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => "Dokumen knowledge base '{$filename}' tidak ditemukan.",
            ], 404);
        }

        $content = File::get($fullPath);

        return response()->json([
            'success' => true,
            'data' => $this->formatDocumentMeta($filename, $fullPath, $content, true),
        ]);
    }

    /**
     * Add or append a new entry/section to a Knowledge Base document (creates file if not yet present).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => ['required', 'string', 'max:120'],
            'section_title' => ['nullable', 'string', 'max:200'],
            'content' => ['required', 'string', 'min:3', 'max:100000'],
            'mode' => ['nullable', 'in:append,replace'],
        ], [
            'document.required' => 'Nama dokumen knowledge base wajib diisi.',
            'content.required' => 'Isi konten knowledge base wajib diisi.',
            'content.min' => 'Konten minimal 3 karakter.',
            'mode.in' => 'Mode harus bernilai append atau replace.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $filename = $this->normalizeDocumentFilename($validated['document']);

        if ($filename === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nama dokumen tidak valid. Gunakan format LUNA_XX_NamaDokumen.md (contoh: LUNA_03_KategoriLayanan.md).',
                'errors' => ['document' => ['Format nama dokumen tidak sesuai standar LUNA_XX_Nama.md.']],
            ], 422);
        }

        $kbPath = $this->kbPath();
        File::ensureDirectoryExists($kbPath);

        $fullPath = $kbPath.DIRECTORY_SEPARATOR.$filename;
        $existed = File::exists($fullPath);
        $mode = $validated['mode'] ?? 'append';

        $newBlock = trim($validated['content']);
        if (! empty($validated['section_title'])) {
            $sectionHeading = '## '.trim($validated['section_title']);
            $newBlock = $sectionHeading."\n\n".$newBlock;
        }

        if ($mode === 'replace' || ! $existed) {
            $finalContent = $newBlock."\n";
        } else {
            $existing = rtrim(File::get($fullPath));
            $finalContent = $existing === ''
                ? $newBlock."\n"
                : $existing."\n\n".$newBlock."\n";
        }

        File::put($fullPath, $finalContent);

        return response()->json([
            'success' => true,
            'message' => $existed
                ? ($mode === 'replace'
                    ? "Dokumen '{$filename}' berhasil diperbarui."
                    : "Entri baru berhasil ditambahkan ke '{$filename}'.")
                : "Dokumen knowledge base '{$filename}' berhasil dibuat.",
            'data' => $this->formatDocumentMeta($filename, $fullPath, $finalContent, true),
        ], $existed ? 200 : 201);
    }

    /**
     * Full replace / upsert of a specific Knowledge Base document (ideal for syncing from external AI repo).
     */
    public function update(Request $request, string $document): JsonResponse
    {
        $filename = $this->normalizeDocumentFilename($document);
        if ($filename === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nama dokumen tidak valid. Gunakan format LUNA_XX_NamaDokumen.md.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'content' => ['required', 'string', 'min:3', 'max:100000'],
        ], [
            'content.required' => 'Isi konten markdown wajib diisi.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $kbPath = $this->kbPath();
        File::ensureDirectoryExists($kbPath);

        $fullPath = $kbPath.DIRECTORY_SEPARATOR.$filename;
        $finalContent = rtrim($validator->validated()['content'])."\n";
        File::put($fullPath, $finalContent);

        return response()->json([
            'success' => true,
            'message' => "Dokumen knowledge base '{$filename}' berhasil disinkronkan.",
            'data' => $this->formatDocumentMeta($filename, $fullPath, $finalContent, true),
        ]);
    }

    /**
     * Delete a Knowledge Base document.
     */
    public function destroy(string $document): JsonResponse
    {
        $filename = $this->normalizeDocumentFilename($document);
        if ($filename === null) {
            return response()->json([
                'success' => false,
                'message' => 'Nama dokumen tidak valid. Gunakan format LUNA_XX_NamaDokumen.md.',
            ], 422);
        }

        $fullPath = $this->kbPath().DIRECTORY_SEPARATOR.$filename;
        if (! File::exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => "Dokumen knowledge base '{$filename}' tidak ditemukan.",
            ], 404);
        }

        File::delete($fullPath);

        return response()->json([
            'success' => true,
            'message' => "Dokumen knowledge base '{$filename}' berhasil dihapus.",
        ]);
    }

    private function kbPath(): string
    {
        return rtrim((string) config('services.luna.kb_path', storage_path('app/luna-knowledge-base')), '/\\');
    }

    /**
     * Validate and normalize document name to safe LUNA_XX_Name.md format.
     */
    private function normalizeDocumentFilename(string $document): ?string
    {
        $trimmed = trim($document);

        if (! preg_match('/^LUNA_\d{2}_[A-Za-z0-9_-]+(\.md)?$/', $trimmed)) {
            return null;
        }

        return str_ends_with($trimmed, '.md') ? $trimmed : $trimmed.'.md';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDocumentMeta(string $filename, string $fullPath, string $content, bool $includeContent): array
    {
        $meta = [
            'filename' => $filename,
            'key' => pathinfo($filename, PATHINFO_FILENAME),
            'is_standard' => in_array($filename, self::STANDARD_DOCUMENTS, true),
            'size_bytes' => strlen($content),
            'sha256' => hash('sha256', $content),
            'updated_at' => Carbon::createFromTimestamp(File::lastModified($fullPath))->toIso8601String(),
        ];

        if ($includeContent) {
            $meta['content'] = $content;
        }

        return $meta;
    }
}

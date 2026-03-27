<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class TaskAttachmentController extends Controller
{
    /**
     * Store a new attachment for a task.
     */
    public function store(Request $request, Project $project, Task $task)
    {
        $user = auth()->user();
        $isAccepted = $project->participants()
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->exists();

        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        if (! $isAccepted) {
            return response()->json(['message' => 'Unauthorized. Only project participants can upload files.'], 403);
        }

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB max
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value instanceof UploadedFile && $this->isBlockedMediaFile($value)) {
                        $fail('Images, audio, and video files are not allowed.');
                    }
                },
            ],
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $aiValidation = $this->validateFileWithAI($project, $task, $file);
            if (! $aiValidation['ok']) {
                return response()->json([
                    'message' => 'File validation failed against project/task requirements.',
                    'reason' => $aiValidation['reason'],
                    'suggestions' => $aiValidation['suggestions'],
                ], 422);
            }

            $path = $file->store('tasks/' . $task->id, 'public');

            $attachment = $task->attachments()->create([
                'uploaded_by' => $user->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            if ($task->status === 'pending') {
                $task->update(['status' => 'in_progress']);
            }

            return response()->json([
                'message' => 'File uploaded successfully.',
                'attachment' => $attachment,
                'task' => $task->fresh(),
                'ai_validation' => [
                    'status' => $aiValidation['validated'] ? 'validated' : 'skipped',
                    'reason' => $aiValidation['reason'],
                ],
            ], 201);
        }

        return response()->json(['message' => 'No file provided'], 400);
    }

    /**
     * Validate uploaded file content against project skills and task details using Gemini.
     */
    private function validateFileWithAI(Project $project, Task $task, UploadedFile $file): array
    {
        $apiKey = (string) config('services.gemini.api_key');
        if ($apiKey === '') {
            return [
                'ok' => false,
                'validated' => false,
                'reason' => 'AI validation service is not configured.',
                'suggestions' => ['Set GEMINI_API_KEY in server environment.'],
            ];
        }

        $content = $this->extractTextContent($file);
        if ($content === null) {
            return [
                'ok' => true,
                'validated' => false,
                'reason' => 'AI validation skipped for this file type.',
                'suggestions' => [],
            ];
        }

        $skills = $project->skills;
        if (is_string($skills)) {
            $decoded = json_decode($skills, true);
            $skills = is_array($decoded) ? $decoded : [$skills];
        }
        $skills = is_array($skills) ? array_values(array_filter($skills)) : [];

        $prompt = $this->buildValidationPrompt($skills, (string) $task->name, (string) ($task->description ?? ''), $content);

        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $endpoint = $baseUrl . '/models/' . $model . ':generateContent';

        try {
            $response = Http::withQueryParameters(['key' => $apiKey])
                ->acceptJson()
                ->timeout((int) config('services.gemini.timeout', 60))
                ->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'maxOutputTokens' => 350,
                    ],
                ]);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'validated' => true,
                'reason' => 'AI validation service is currently unavailable.',
                'suggestions' => ['Try again in a moment.'],
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'validated' => true,
                'reason' => $response->json('error.message') ?? 'AI provider rejected the validation request.',
                'suggestions' => ['Try again later or contact support.'],
            ];
        }

        $rawText = $this->extractGeminiText((array) $response->json());
        [$ok, $reason, $suggestions] = $this->parseValidationResult($rawText);

        return [
            'ok' => $ok,
            'validated' => true,
            'reason' => $reason,
            'suggestions' => $suggestions,
        ];
    }

    private function buildValidationPrompt(array $skills, string $taskName, string $taskDescription, string $fileContent): string
    {
        $skillsText = empty($skills) ? 'No specific project skills provided' : implode(', ', $skills);

        return "You are a strict task submission validator.\n"
            . "Validate the uploaded file against:\n"
            . "1) Project skills: {$skillsText}\n"
            . "2) Task name: {$taskName}\n"
            . "3) Task description: {$taskDescription}\n\n"
            . "Uploaded file content:\n"
            . "```\n{$fileContent}\n```\n\n"
            . "Respond with JSON only (no markdown, no extra text):\n"
            . "{\"decision\":\"accept or reject\",\"reason\":\"short reason\",\"suggestions\":[\"suggestion 1\",\"suggestion 2\"]}\n"
            . "Rules:\n"
            . "- Use decision=accept only if the file clearly satisfies the requirements.\n"
            . "- Otherwise use decision=reject and provide actionable suggestions.";
    }

    private function extractGeminiText(array $data): string
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        $texts = [];

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $texts[] = $part['text'];
            }
        }

        return trim(implode("\n", $texts));
    }

    private function parseValidationResult(string $raw): array
    {
        $json = json_decode($raw, true);

        if (! is_array($json) && preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
            $json = json_decode($matches[0], true);
        }

        if (is_array($json)) {
            $decision = strtolower((string) ($json['decision'] ?? 'reject'));
            $ok = $decision === 'accept';
            $reason = (string) ($json['reason'] ?? ($ok ? 'Accepted by AI validation.' : 'Rejected by AI validation.'));
            $suggestions = $json['suggestions'] ?? [];
            $suggestions = is_array($suggestions) ? array_values(array_map('strval', $suggestions)) : [];

            return [$ok, $reason, $suggestions];
        }

        $normalized = mb_strtolower($raw);
        $ok = str_contains($normalized, 'accept');
        $reason = $ok ? 'Accepted by AI validation.' : 'Rejected by AI validation.';

        return [$ok, $reason, []];
    }

    /**
     * Return text content for supported text/code files. Returns null for unsupported file types.
     */
    private function extractTextContent(UploadedFile $file): ?string
    {
        $allowedExtensions = [
            'txt', 'md', 'php', 'js', 'ts', 'tsx', 'jsx', 'py', 'java', 'kt', 'kts', 'c', 'cpp', 'cs',
            'go', 'rs', 'rb', 'swift', 'sql', 'json', 'xml', 'html', 'css', 'scss', 'vue', 'yaml', 'yml',
            'env', 'sh', 'bat', 'ps1', 'zip', 'docx', 'doc', 'rtf',
        ];

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        if ($extension === 'zip') {
            return $this->extractTextFromZip($file, $allowedExtensions);
        }

        if ($extension === 'docx') {
            return $this->extractTextFromDocx($file);
        }

        if ($extension === 'doc') {
            return $this->extractTextFromDoc($file);
        }

        $content = @file_get_contents($file->getRealPath());
        if ($content === false) {
            return null;
        }

        if ($this->isLikelyBinary($content)) {
            return null;
        }

        // Keep request size reasonable for model validation.
        return mb_substr($content, 0, 30000);
    }

    private function extractTextFromZip(UploadedFile $file, array $allowedExtensions): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            return null;
        }

        $buffer = '';
        $maxChars = 30000;
        $maxFiles = 25;
        $processed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if ($processed >= $maxFiles || mb_strlen($buffer) >= $maxChars) {
                break;
            }

            $entryName = $zip->getNameIndex($i);
            if (! is_string($entryName) || str_ends_with($entryName, '/')) {
                continue;
            }

            $entryExt = strtolower((string) pathinfo($entryName, PATHINFO_EXTENSION));
            if ($entryExt === '' || ! in_array($entryExt, $allowedExtensions, true) || $entryExt === 'zip') {
                continue;
            }

            $entryContent = $zip->getFromIndex($i);
            if (! is_string($entryContent) || $entryContent === '') {
                continue;
            }

            if ($this->isLikelyBinary($entryContent)) {
                continue;
            }

            $processed++;
            $buffer .= "\n\n### FILE: {$entryName}\n";
            $buffer .= mb_substr($entryContent, 0, 5000);
        }

        $zip->close();

        if ($processed === 0) {
            return null;
        }

        return mb_substr(trim($buffer), 0, $maxChars);
    }

    private function extractTextFromDocx(UploadedFile $file): ?string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            return null;
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return null;
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($xml)) ?? '');
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 30000);
    }

    private function extractTextFromDoc(UploadedFile $file): ?string
    {
        $content = @file_get_contents($file->getRealPath());
        if (! is_string($content) || $content === '') {
            return null;
        }

        // Best-effort extraction for legacy .doc binary content.
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\x{0600}-\x{06FF}]/u', ' ', $content);
        $text = trim(preg_replace('/\s+/', ' ', (string) $text) ?? '');

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 30000);
    }

    private function isBlockedMediaFile(UploadedFile $file): bool
    {
        $mime = strtolower((string) $file->getMimeType());
        if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) {
            return true;
        }

        $blockedExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico',
            'mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a',
            'mp4', 'avi', 'mov', 'mkv', 'webm', 'wmv', '3gp', 'mpeg', 'mpg',
        ];

        $extension = strtolower((string) $file->getClientOriginalExtension());

        return in_array($extension, $blockedExtensions, true);
    }

    private function isLikelyBinary(string $content): bool
    {
        if ($content === '') {
            return false;
        }

        if (strpos($content, "\0") !== false) {
            return true;
        }

        $sample = substr($content, 0, 2048);
        if ($sample === '') {
            return false;
        }

        $printable = preg_match_all('/[\x09\x0A\x0D\x20-\x7E]/', $sample) ?: 0;
        $ratio = $printable / max(strlen($sample), 1);

        return $ratio < 0.7;
    }

    /**
     * Remove an attachment.
     */
    public function destroy(Project $project, Task $task, TaskAttachment $attachment)
    {
        $user = auth()->user();
        $isAdmin = $user->role === 'system_admin';

        $isProjectAdmin = $project->participants()
            ->where('user_id', $user->id)
            ->where('role', 'team_admin')
            ->where('status', 'accepted')
            ->exists();

        if ($attachment->task_id !== $task->id || $task->project_id !== $project->id) {
            return response()->json(['message' => 'Mismatch'], 400);
        }

        // Only uploader, project admin, or system admin can delete
        if (! $isAdmin && ! $isProjectAdmin && $attachment->uploaded_by !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class AIController extends Controller
{
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:8000',
            'requirements' => 'nullable|string|max:20000',
            'file_content' => 'nullable|string|max:200000',
            'system_prompt' => 'nullable|string|max:2000',
            'model' => 'nullable|string|max:120',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'max_output_tokens' => 'nullable|integer|min:1|max:2000',
        ]);

        $message = $this->buildPrompt($validated);

        if ($message === '') {
            return response()->json([
                'message' => 'Either "message" or both "requirements" and "file_content" are required.',
            ], 422);
        }

        $apiKey = config('services.gemini.api_key');

        if (! $apiKey) {
            return response()->json([
                'message' => 'Gemini API key is not configured on server.',
            ], 500);
        }

        $model = $validated['model'] ?? config('services.gemini.model', 'gemini-2.5-flash');
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $message],
                    ],
                ],
            ],
        ];

        if (! empty($validated['system_prompt'])) {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $validated['system_prompt']],
                ],
            ];
        }

        $generationConfig = [];

        if (isset($validated['temperature'])) {
            $generationConfig['temperature'] = (float) $validated['temperature'];
        }

        if (isset($validated['max_output_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $validated['max_output_tokens'];
        }

        if (! empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        try {
            $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
            $endpoint = $baseUrl.'/models/'.$model.':generateContent';

            $response = Http::withQueryParameters(['key' => $apiKey])
                ->acceptJson()
                ->timeout((int) config('services.gemini.timeout', 60))
                ->post($endpoint, $payload);

            if (! $response->successful()) {
                return response()->json([
                    'message' => 'AI provider request failed.',
                    'provider_error' => $response->json('error.message') ?? 'Unknown provider error',
                ], $response->status());
            }

            $data = $response->json();
            $reply = $this->extractText($data);

            return response()->json([
                'id' => $data['responseId'] ?? null,
                'model' => $model,
                'reply' => $reply,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'AI provider connection failed.',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    private function extractText(array $data): string
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

    private function buildPrompt(array $validated): string
    {
        if (! empty($validated['requirements']) && ! empty($validated['file_content'])) {
            $requirements = $validated['requirements'];
            $fileContent = $validated['file_content'];

            return "تفقد الكود التالي بناءً على المتطلبات التالية:\n\n"
                ."المتطلبات:\n"
                .$requirements
                ."\n\n"
                ."الكود:\n"
                ."```\n"
                .$fileContent
                ."\n```\n\n"
                ."هل الكود متوافق مع المتطلبات المذكورة؟\n"
                ."أريد رداً مختصراً جداً.\n"
                ."إذا كان متوافقاً، أجب بكلمة \"نعم\" فقط.\n"
                ."أما إذا لم يكن متوافقاً، أجب بـ \"لا\" فقط، وفي السطر التالي أعطني التعديلات اللازمة.\n"
                ."يرجى الإجابة باللغة العربية حصراً.";
        }

        return (string) ($validated['message'] ?? '');
    }
}

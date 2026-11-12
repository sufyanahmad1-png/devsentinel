<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DevSentinelService
{
    protected string $apiKey;
    protected string $model = 'gemini-3.5-flash-lite';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
    }

    /**
     * Diagnose exception using Google Gemini AI.
     */
    public function diagnose(Throwable $exception): array
    {
        $file = $exception->getFile();
        $line = $exception->getLine();
        $message = $exception->getMessage();
        $codeSnippet = $this->getCodeSnippet($file, $line);

        $prompt = <<<EOT
You are an expert Laravel and PHP developer assistant.
An unhandled exception occurred in a Laravel application. Analyze the error and provide a precise fix.

Error Message: {$message}
File: {$file}
Failing Line: {$line}

Code Context around the error:
```php
{$codeSnippet}
```

Please respond in JSON with the following keys:
1. "root_cause": (1-2 sentences explaining why this error happened in plain English)
2. "suggested_fix": (Brief instruction on how to fix it)
3. "fixed_code": (The corrected PHP code snippet)
4. "severity": ("High", "Medium", or "Low")
Only output valid JSON without markdown wrapping.
EOT;

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::withOptions([
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]
            ])
            ->retry(3, 1000, throw: false)
            ->timeout(25)
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $parts = $response->json('candidates.0.content.parts', []);
                $rawText = '';
                
                foreach ($parts as $part) {
                    if (empty($part['thought']) && isset($part['text'])) {
                        $rawText = $part['text'];
                        break;
                    }
                }

                if (empty($rawText) && !empty($parts)) {
                    $rawText = end($parts)['text'] ?? '';
                }

                $cleanedJson = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($rawText));
                $decoded = json_decode($cleanedJson, true);

                return [
                    'status' => 'success',
                    'model' => $this->model,
                    'file' => $file,
                    'line' => $line,
                    'error' => $message,
                    'analysis' => $decoded ?? ['raw_response' => $rawText]
                ];
            }

            return [
                'status' => 'api_error',
                'details' => $response->body()
            ];

        } catch (Throwable $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract lines of code around the failure.
     */
    protected function getCodeSnippet(string $filePath, int $failingLine, int $padding = 5): string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return "// File could not be read.";
        }

        $lines = file($filePath);
        $start = max(0, $failingLine - $padding - 1);
        $length = ($padding * 2) + 1;
        $slice = array_slice($lines, $start, $length, true);

        $snippet = "";
        foreach ($slice as $lineNum => $lineContent) {
            $currentLine = $lineNum + 1;
            $marker = ($currentLine === $failingLine) ? " > " : "   ";
            $snippet .= sprintf("%s%4d | %s", $marker, $currentLine, $lineContent);
        }

        return $snippet;
    }
}

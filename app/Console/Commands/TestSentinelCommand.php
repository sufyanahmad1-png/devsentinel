<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DevSentinelService;
use App\Services\GitHubPatchService;
use Throwable;

class TestSentinelCommand extends Command
{
    protected $signature = 'sentinel:test';
    protected $description = 'Trigger a synthetic error, diagnose via AI, and auto-create a GitHub Pull Request';

    public function handle(DevSentinelService $sentinel, GitHubPatchService $gitService): int
    {
        $this->info("⚡ Triggering a synthetic runtime error...");

        try {
            // Intentionally triggering a DivisionByZeroError
            $numerator = 1000;
            $divisor = $undefinedRate ?? 0;
            $result = $numerator / $divisor;
            $this->info("Result: $result");
        } catch (Throwable $e) {
            $this->warn("Caught exception: " . $e->getMessage());
            $this->info("🤖 Contacting Gemini AI for diagnosis...");

            $analysis = $sentinel->diagnose($e);

            if (($analysis['status'] ?? '') === 'success') {
                $this->info("✅ AI Diagnosis successfully received!");
                $this->line("Root Cause: " . ($analysis['analysis']['root_cause'] ?? 'N/A'));
                $this->line("Suggested Fix: " . ($analysis['analysis']['suggested_fix'] ?? 'N/A'));
                
                $this->newLine();
                $this->info("🚀 Dispatching automated Hotfix to GitHub...");

                $prResult = $gitService->createHotfixPullRequest($analysis);

                if (($prResult['status'] ?? '') === 'success') {
                    $this->newLine();
                    $this->info("==========================================================");
                    $this->info("🎉 SUCCESS: GitHub Hotfix Pull Request Automatically Created!");
                    $this->info("👉 PR URL: " . $prResult['pr_url']);
                    $this->info("👉 Branch: " . $prResult['branch']);
                    $this->info("==========================================================");
                } else {
                    $this->error("❌ GitHub PR Creation Failed: " . ($prResult['error'] ?? 'Unknown'));
                }
            } else {
                $this->error("❌ Diagnosis returned with status: " . ($analysis['status'] ?? 'unknown'));
            }
        }

        return Command::SUCCESS;
    }
}

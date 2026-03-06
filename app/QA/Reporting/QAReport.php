<?php

namespace App\QA\Reporting;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QAReport
{
    protected array $bots;
    protected array $history;
    protected array $exploits;

    public function __construct(array $bots, array $history, array $exploits)
    {
        $this->bots = $bots;
        $this->history = $history;
        $this->exploits = $exploits;
    }

    public function generate(): string
    {
        $reportFilename = 'qa_report_' . now()->format('Y_m_d_His') . '.md';
        $content = $this->buildMarkdown();
        
        // Save to public storage for easy access
        Storage::disk('public')->put("reports/{$reportFilename}", $content);

        return $reportFilename;
    }

    protected function buildMarkdown(): string
    {
        $md = "# 🛡️ RACKORA: MEGA QA SIMULATION REPORT\n\n";
        $md .= "> **STATUS: COMPLETE** | **DATE: " . now()->toDateTimeString() . "**\n\n";

        $md .= "## 📊 OVERVIEW SUMMARY\n\n";
        $lastMetric = end($this->history);
        $md .= "| Metric | Final value |\n";
        $md .= "| --- | --- |\n";
        $md .= "| **Bot Count** | " . count($this->bots) . " |\n";
        $md .= "| **Avg. Balance** | $" . number_format($lastMetric['avg_balance'] ?? 0, 2) . " |\n";
        $md .= "| **Total Wealth** | $" . number_format($lastMetric['total_wealth'] ?? 0, 2) . " |\n";
        $md .= "| **Avg. Reputation** | " . number_format($lastMetric['avg_reputation'] ?? 0, 1) . " |\n";
        $md .= "| **Total Racks Deployed** | " . ($lastMetric['total_racks'] ?? 0) . " |\n";
        $md .= "| **Total Servers Deployed** | " . ($lastMetric['total_servers'] ?? 0) . " |\n";
        $md .= "\n";

        $md .= "## 🤖 BOT PROFILES & STRATEGIES\n\n";
        foreach ($this->bots as $bot) {
            $u = $bot->getUser();
            $md .= "- **{$u->name}** ({$bot->getStrategyName()}): Balance $" . number_format($u->economy->balance, 2) . ", LVL {$u->economy->level}, XP {$u->economy->experience}\n";
        }
        $md .= "\n";

        $md .= "## 🚨 EXPLOIT & ANOMALY DETECTION\n\n";
        if (empty($this->exploits)) {
            $md .= "✅ **NO EXPLOITS DETECTED DURING THIS RUN.**\n\n";
        } else {
            $md .= "⚠️ **CRITICAL: " . count($this->exploits) . " ANOMALIES DETECTED.**\n\n";
            $md .= "| Timestamp | Bot | Message | State snapshot |\n";
            $md .= "| --- | --- | --- | --- |\n";
            foreach ($this->exploits as $ex) {
                $md .= "| {$ex['timestamp']} | User #{$ex['user_id']} | {$ex['message']} | " . json_encode($ex['state']) . " |\n";
            }
            $md .= "\n";
        }

        $md .= "## 📈 WEALTH GROWTH TOPOLOGY\n\n";
        $md .= "```mermaid\ngraph LR\n";
        $ticks = array_keys($this->history);
        $md .= "Start --> Tick_" . min($ticks) . " --> Halftime_" . (int)(count($ticks)/2) . " --> End\n";
        $md .= "```\n\n";

        $md .= "### Performance Metrics History\n\n";
        $md .= "| Tick | Avg. Wealth | Total Servers | Avg. Rep |\n";
        $md .= "| --- | --- | --- | --- |\n";
        $step = max(1, (int)(count($this->history) / 10)); // Capture 10 sample points
        foreach($this->history as $tick => $data) {
            if ($tick % $step == 0) {
                 $md .= "| {$tick} | $" . number_format($data['avg_balance'], 2) . " | {$data['total_servers']} | {$data['avg_reputation']} |\n";
            }
        }

        return $md;
    }
}

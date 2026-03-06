# 🛡️ RACKORA: MEGA QA AUTOMATION SYSTEM

This is a comprehensive automated testing and simulation framework for the Rackora game. It simulates thousands of player actions, detects exploits, and monitors economy stability.

## 🚀 Key Components

### 1. **Automated Gameplay Bots** (`app/QA/Bots/`)
Simulates different player behaviors with unique strategies:
- **Beginner Bot**: Conservative growth, basic racks, slow expansion.
- **Expansion Bot**: Rapid scaling, aggressive datacenter purchasing.
- **Optimization Bot**: Focuses on energy efficiency and high ROI contracts.
- **Aggressive Investor**: High-leverage investments, large infrastructure focus.
- **Chaos Bot**: Performs random actions to test edge cases and non-linear paths.

### 2. **Game Action Simulator** (`app/QA/GameActionSimulator.php`)
Translates bot intents into real backend API calls. It uses Sanctum authentication and simulates the full Laravel request lifecycle to ensure all middlewares and validations are tested.

### 3. **Exploit & Anomaly Detection** (`app/QA/Exploits/ExploitDetector.php`)
Monitors player states for illegal conditions:
- **Infinite Money Loops**: Detection of abnormal balance jumps.
- **Negative Energy Costs**: Ensuring power consumption never generates revenue.
- **Duplicate Contracts**: Detecting spam attacks or race conditions.
- **Balance Overflows**: Catching cases where wealth drops below zero.

### 4. **Reporting System** (`app/QA/Reporting/QAReport.php`)
Generates detailed Markdown reports after each run, including:
- Bot performance (Wealth, Reputation, Infrastructure).
- Economy growth topology (Mermaid diagrams).
- Exploits detected (with trace and snapshots).
- Performance metrics history.

## 🛠️ Usage

To run a simulation, use the `qa:mega` Artisan command:

```bash
# Run with 50 bots for 100 ticks (simulated hours/cycles)
php artisan qa:mega --bots=50 --ticks=100

# Run a clean simulation (reset bot pool)
php artisan qa:mega --bots=20 --ticks=50 --clean
```

### Report Locations
Reports are saved as Markdown artifacts in:
`storage/app/public/reports/qa_report_YYYY_MM_DD_HHMMSS.md`

## 📊 Stress & Performance
The system supports simulating hundreds of concurrent bot players. It monitors API latency and database performance, logging any bottlenecks to the Laravel logs.

---
**Senior QA Automation Architect**
*Simulation testing suite for Rackora Infrastructure.*

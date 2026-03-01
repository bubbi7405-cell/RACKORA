<?php

namespace App\Services\Game;

use App\Models\GameConfig;

class FormulaEvaluator
{
    /**
     * Evaluate a formula with given variables.
     * Uses a very simple and safe substitution approach.
     * Formula example: "base + (power_failures * 0.03) + (heat_events * 0.02)"
     */
    public static function evaluate(string $configKey, array $variables, float $default = 0): float
    {
        $formula = GameConfig::get($configKey);
        
        if (!$formula || !is_string($formula)) {
            return $default;
        }

        return self::calculate($formula, $variables);
    }

    /**
     * The core calculation logic.
     * WARNING: Avoid using eval(). Instead, substitute and use a math parser.
     */
    public static function calculate(string $formula, array $variables): float
    {
        // 1. Replace variables
        $expression = $formula;
        foreach ($variables as $name => $value) {
            $expression = str_replace($name, (float)$value, $expression);
        }

        // 2. Cleanup (safety)
        $expression = preg_replace('/[^0-9\.\+\-\*\/\(\)\s]/', '', $expression);

        // 3. Simple evaluation if possible, or fallback to 0
        try {
            // Since we can't easily install a math library, we'll use a basic approach.
            // For complex tasks, we'd use 'MathExecutor' or similar.
            // Here we use a limited eval() ONLY after strict regex cleanup.
            
            if (empty($expression)) return 0;
            
            return (float) eval("return {$expression};");
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

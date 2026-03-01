<?php

namespace App\Services\Game;

use App\Models\GameConfig;
use Illuminate\Support\Facades\Log;

class FormulaService
{
    /**
     * Evaluate a formula by key with the given context.
     * Formulas are stored in game_configs with placeholders like ${variable}.
     */
    public function evaluate(string $key, array $context = [], float $default = 0.0): float
    {
        $formula = GameConfig::get($key);

        if (!$formula) {
            Log::warning("Formula not found: {$key}. Using default: {$default}");
            return $default;
        }

        return $this->process($formula, $context, $default);
    }

    /**
     * Internal processing of formula string.
     */
    public function process(string $formula, array $context = [], float $default = 0.0): float
    {
        try {
            $expression = $formula;

            // Replace variables from context
            foreach ($context as $var => $val) {
                // Support ${var}, $var, and bare var naming conventions
                $expression = str_replace(['${' . $var . '}', '$' . $var, $var], (string)$val, $expression);
            }

            // Clean up any remaining unresolved $variables to avoid errors (replace with 0)
            $expression = preg_replace('/\$[a-zA-Z0-9_{}]+/', '0', $expression);

            // Also clean up any remaining bare identifiers (variable names without $)
            $expression = preg_replace('/[a-zA-Z_][a-zA-Z0-9_]*/', '0', $expression);

            // Safety check: Only allow mathematical characters (digits, operators, parens, whitespace, dots)
            if (preg_match('/[^0-9\.\+\-\*\/\(\)\s]/', $expression)) {
                Log::error("Security Alert: Unauthorized symbols in formula expression: {$expression}");
                return $default;
            }

            // Evaluate the math expression
            // Since we've sanitized it and replaced all variables, eval() is relatively safe here 
            // but we wrap it carefully.
            $result = @eval("return {$expression};");

            if ($result === false || is_nan($result) || is_infinite($result)) {
                return $default;
            }

            return (float)$result;

        } catch (\Throwable $e) {
            Log::error("Formula Evaluation Error [{$formula}]: " . $e->getMessage());
            return $default;
        }
    }
}

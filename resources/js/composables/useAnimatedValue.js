/**
 * useAnimatedValue — Smooth number tweening for dashboard displays.
 *
 * Provides a smoothly transitioning display value that animates
 * from old to new when the source value changes.
 *
 * Usage:
 *   const { display, isAnimating, flashClass } = useAnimatedValue(
 *       () => economy.balance,
 *       { duration: 600, decimals: 2 }
 *   );
 *
 *   <span :class="flashClass">{{ display }}</span>
 */
import { ref, computed, watch } from 'vue';

export function useAnimatedValue(valueGetter, options = {}) {
    const {
        duration = 500,
        decimals = 0,
        flashThreshold = 0.05, // Flash if change > 5%
    } = options;

    const displayValue = ref(0);
    const isAnimating = ref(false);
    const lastDirection = ref(null); // 'up', 'down', null
    let animationId = null;

    const sourceValue = computed(() => {
        const val = typeof valueGetter === 'function' ? valueGetter() : valueGetter?.value;
        return typeof val === 'number' ? val : 0;
    });

    // Formatted display string
    const display = computed(() => {
        if (decimals === 0) return Math.round(displayValue.value).toLocaleString();
        return displayValue.value.toFixed(decimals);
    });

    // Flash class applied briefly on significant changes
    const flashClass = ref('');

    function animateTo(target) {
        if (animationId) cancelAnimationFrame(animationId);

        const start = displayValue.value;
        const diff = target - start;
        if (Math.abs(diff) < 0.001) {
            displayValue.value = target;
            return;
        }

        // Determine direction
        lastDirection.value = diff > 0 ? 'up' : 'down';

        // Check if change is significant enough to flash
        const percentChange = Math.abs(diff / (start || 1));
        if (percentChange >= flashThreshold) {
            flashClass.value = diff > 0 ? 'ds-flash-up' : 'ds-flash-down';
            setTimeout(() => { flashClass.value = ''; }, 800);
        }

        isAnimating.value = true;
        const startTime = performance.now();

        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Ease-out cubic
            const eased = 1 - Math.pow(1 - progress, 3);

            displayValue.value = start + diff * eased;

            if (progress < 1) {
                animationId = requestAnimationFrame(step);
            } else {
                displayValue.value = target;
                isAnimating.value = false;
                animationId = null;
            }
        }

        animationId = requestAnimationFrame(step);
    }

    // Initialize immediately
    displayValue.value = sourceValue.value;

    // Watch for changes and animate
    watch(sourceValue, (newVal) => {
        animateTo(newVal);
    });

    return {
        display,
        raw: displayValue,
        isAnimating,
        lastDirection,
        flashClass,
    };
}

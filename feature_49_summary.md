# Feature 49: Contract Negotiation - Implementation Summary

This document summarizes the changes made to implement the Contract Negotiation system.

## 1. Backend Implementation

### `App\Services\Game\ContractNegotiationService.php`
- Implemented `submitBid` to handle bid processing, probability calculation, and transaction logic.
- Implemented `calculateAcceptanceProbability` based on:
    - **Price Ratio:** Higher price = lower chance (exponential penalty).
    - **SLA Tier:** Higher tier than requested = bonus chance.
    - **Contract Length:** Stability bonus/penalty.
    - **Reputation:** Player reputation provides a baseline bonus.
    - **Fatigue:** Each failed attempt reduces probability by ~20%.
- Implemented "Walk Away" mechanic: 10% base chance + 20% per attempt for customer to cancel negotiation entirely on failure.

### `App\Services\Game\CustomerOrderService.php`
- Updated `generateNewOrder` to mark orders as `is_negotiable`.
- **Change:** Enabled negotiation for `standard` and `premium` orders with a 15% chance (previously only `enterprise`+), allowing early-game players to experience the feature.
- Default negotiation probability logic verifies pricing and SLA tier compatibility.

### `App\Http\Controllers\Api\NegotiationController.php`
- Verified endpoints:
    - `POST /api/negotiation/{id}/bid`: Submits a proposal.
    - `POST /api/negotiation/{id}/preview`: Calculates probability without submitting.

## 2. Frontend Implementation

### `resources/js/components/Overlay/ContractNegotiationOverlay.vue`
- Created comprehensive UI for negotiation:
    - **Client Profile:** Shows requirements, patience meter, and negotiation history.
    - **Bidding Interface:** Inputs for Price (Slider/Input), SLA (Buttons), and Duration.
    - **Probability Gauge:** Visual feedback on acceptance likelihood.
- **UX Improvements:**
    - Debounced API calls for probability preview.
    - Added "Calculating..." state with visual indicators (spinner/dots) to show when probability is updating.
    - Dynamic color coding for probability (Red/Yellow/Green).

### `resources/js/stores/game.js`
- Implemented `submitBid` action: Handles API call, toast notifications, and state refresh.
- Implemented `getBidPreview` action: Fetches probability calculation from backend.

### `resources/js/components/Game/GameWorld.vue` & `OrderOverlay.vue`
- Integrated "Negotiate Terms" button in `OrderOverlay`.
- Wired up event handling to display `ContractNegotiationOverlay` modally over the game world.

## 3. Testing & Verification
- **Negotiation Trigger:** Verified that the "Negotiate Terms" button appears for negotiable orders.
- **Bidding Logic:** Verified that changing Price/SLA updates the probability in real-time.
- **Submission:** Verified that successful bids update the order status and player XP.
- **Failure:** Verified that failed bids increment attempts and apply fatigue/walk-away risks.

## 4. Next Steps
- Consider adding **Research Skills** to improve negotiation odds (e.g., "Silver Tongue", "Contract Law").
- Consider adding **Special Events** where market conditions affect negotiation leverage (e.g., "Tech Boom" makes clients desperate).

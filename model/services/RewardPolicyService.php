<?php

require_once __DIR__ . '/../repository/RewardPolicyRepository.php';
require_once __DIR__ . '/ActivityLogService.php';

/**
 * RewardPolicyService
 *
 * Provides business logic for reward policy management including
 * creation, update, and deletion with constraint enforcement.
 * Uses RewardPolicyRepository for data access and ActivityLogService
 * for audit logging.
 *
 * @see Requirement 5.1 — Create reward policy with severity and reward amount
 * @see Requirement 5.2 — Support severity levels: critical, high, medium, low, informational
 * @see Requirement 5.3 — Update reward policy and log activity
 * @see Requirement 5.4 — Delete policy only if no accepted reports reference it
 * @see Requirement 5.5 — Reject deletion with error if accepted reports exist
 */
class RewardPolicyService
{
    private RewardPolicyRepository $rewardPolicyRepository;
    private ActivityLogService $activityLogService;

    /**
     * @param RewardPolicyRepository $rewardPolicyRepository Repository for reward policy data access.
     * @param ActivityLogService     $activityLogService     Service for audit logging.
     */
    public function __construct(
        RewardPolicyRepository $rewardPolicyRepository,
        ActivityLogService $activityLogService
    ) {
        $this->rewardPolicyRepository = $rewardPolicyRepository;
        $this->activityLogService = $activityLogService;
    }

    /**
     * Create a new reward policy for a program.
     *
     * Validates severity level and reward amounts before creating the policy.
     * Logs the creation event.
     *
     * @param int    $programId  Program ID to associate the policy with.
     * @param string $severity   Severity level (critical, high, medium, low, informational).
     * @param float  $minReward  Minimum reward amount (USD).
     * @param float  $maxReward  Maximum reward amount (USD).
     * @param int    $userId     Acting user's ID (for audit log).
     * @param string $ipAddress  Client IP address (for audit log).
     * @return array ['success' => bool, 'errors' => array, 'policy_id' => int|null]
     */
    public function createPolicy(
        int $programId,
        string $severity,
        float $minReward,
        float $maxReward,
        int $userId,
        string $ipAddress = ''
    ): array {
        $errors = [];

        // Validate severity level
        if (!in_array($severity, RewardPolicyRepository::SEVERITY_LEVELS, true)) {
            $errors['severity'] = 'Invalid severity level. Must be one of: critical, high, medium, low, informational';
        }

        // Validate reward amounts
        if ($minReward < 0) {
            $errors['min_reward'] = 'Minimum reward must be zero or greater';
        }
        if ($maxReward < 0) {
            $errors['max_reward'] = 'Maximum reward must be zero or greater';
        }
        if ($minReward >= 0 && $maxReward >= 0 && $maxReward < $minReward) {
            $errors['max_reward'] = 'Maximum reward must be greater than or equal to minimum reward';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'policy_id' => null];
        }

        // Create the policy
        $policyId = $this->rewardPolicyRepository->create($programId, $severity, $minReward, $maxReward);

        // Log the activity
        $this->activityLogService->log(
            $userId,
            'reward_policy.create',
            'reward_policy',
            $policyId,
            [
                'program_id' => $programId,
                'severity' => $severity,
                'min_reward' => $minReward,
                'max_reward' => $maxReward,
            ],
            $ipAddress
        );

        return ['success' => true, 'errors' => [], 'policy_id' => $policyId];
    }

    /**
     * Update an existing reward policy's reward amounts.
     *
     * Validates reward amounts before updating. Logs the update event.
     *
     * @param int    $policyId   Reward policy ID to update.
     * @param float  $minReward  New minimum reward amount (USD).
     * @param float  $maxReward  New maximum reward amount (USD).
     * @param int    $userId     Acting user's ID (for audit log).
     * @param string $ipAddress  Client IP address (for audit log).
     * @return array ['success' => bool, 'errors' => array]
     */
    public function updatePolicy(
        int $policyId,
        float $minReward,
        float $maxReward,
        int $userId,
        string $ipAddress = ''
    ): array {
        $errors = [];

        // Check policy exists
        $policy = $this->rewardPolicyRepository->findById($policyId);
        if ($policy === null) {
            $errors['policy'] = 'Reward policy not found';
            return ['success' => false, 'errors' => $errors];
        }

        // Validate reward amounts
        if ($minReward < 0) {
            $errors['min_reward'] = 'Minimum reward must be zero or greater';
        }
        if ($maxReward < 0) {
            $errors['max_reward'] = 'Maximum reward must be zero or greater';
        }
        if ($minReward >= 0 && $maxReward >= 0 && $maxReward < $minReward) {
            $errors['max_reward'] = 'Maximum reward must be greater than or equal to minimum reward';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Update the policy
        $this->rewardPolicyRepository->update($policyId, $minReward, $maxReward);

        // Log the activity
        $this->activityLogService->log(
            $userId,
            'reward_policy.update',
            'reward_policy',
            $policyId,
            [
                'previous_min_reward' => (float) $policy['min_reward'],
                'previous_max_reward' => (float) $policy['max_reward'],
                'new_min_reward' => $minReward,
                'new_max_reward' => $maxReward,
            ],
            $ipAddress
        );

        return ['success' => true, 'errors' => []];
    }

    /**
     * Delete a reward policy with constraint check.
     *
     * Prevents deletion if any accepted reports reference the policy.
     * Logs the deletion event on success.
     *
     * @param int    $policyId   Reward policy ID to delete.
     * @param int    $userId     Acting user's ID (for audit log).
     * @param string $ipAddress  Client IP address (for audit log).
     * @return array ['success' => bool, 'errors' => array]
     */
    public function deletePolicy(
        int $policyId,
        int $userId,
        string $ipAddress = ''
    ): array {
        $errors = [];

        // Check policy exists
        $policy = $this->rewardPolicyRepository->findById($policyId);
        if ($policy === null) {
            $errors['policy'] = 'Reward policy not found';
            return ['success' => false, 'errors' => $errors];
        }

        // Check constraint: no accepted reports should reference this policy
        if ($this->rewardPolicyRepository->hasAcceptedReports($policyId)) {
            $errors['policy'] = 'Cannot delete this reward policy because it is referenced by accepted reports';
            return ['success' => false, 'errors' => $errors];
        }

        // Delete the policy
        $this->rewardPolicyRepository->delete($policyId);

        // Log the activity
        $this->activityLogService->log(
            $userId,
            'reward_policy.delete',
            'reward_policy',
            $policyId,
            [
                'program_id' => (int) $policy['program_id'],
                'severity' => $policy['severity'],
                'min_reward' => (float) $policy['min_reward'],
                'max_reward' => (float) $policy['max_reward'],
            ],
            $ipAddress
        );

        return ['success' => true, 'errors' => []];
    }
}

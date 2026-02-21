<?php
/**
 * Poll Helper Functions
 * Shared functions for poll filtering and display logic
 */

/**
 * Filter polls based on user's roles and poll settings
 * 
 * @param array $polls Array of poll records from database
 * @param string $userRole User's system role
 * @param array $userAzureRoles User's Azure/Entra roles
 * @return array Filtered array of polls visible to the user
 */
function filterPollsForUser($polls, $userRole, $userAzureRoles = []) {
    return array_filter($polls, function($poll) use ($userRole, $userAzureRoles) {
        // Skip if user has manually hidden this poll
        if (!empty($poll['user_has_hidden']) && $poll['user_has_hidden'] > 0) {
            return false;
        }
        
        // If visible_to_all is set, always show
        if (!empty($poll['visible_to_all'])) {
            // For internal polls, hide if user has already voted
            if (!empty($poll['is_internal']) && !empty($poll['user_has_voted']) && $poll['user_has_voted'] > 0) {
                return false;
            }
            return true;
        }
        
        // Check target_roles (Entra-based visibility): if set, user must have at least one matching role
        $targetRoles = !empty($poll['target_roles']) ? json_decode($poll['target_roles'], true) : null;
        if ($targetRoles !== null && is_array($targetRoles) && count($targetRoles) > 0) {
            $hasMatchingRole = false;
            if (is_array($userAzureRoles)) {
                foreach ($userAzureRoles as $userAzureRole) {
                    if (in_array($userAzureRole, $targetRoles)) {
                        $hasMatchingRole = true;
                        break;
                    }
                }
            }
            if (!$hasMatchingRole) {
                return false;
            }
        }

        // Check allowed_roles (Entra roles) if set
        $allowedRoles = !empty($poll['allowed_roles']) ? json_decode($poll['allowed_roles'], true) : null;
        if ($allowedRoles !== null) {
            // Validate that decoded value is an array
            if (!is_array($allowedRoles)) {
                // Invalid JSON or not an array, skip role check
                $allowedRoles = null;
            } else {
                // Check if any of user's azure_roles match allowed_roles
                $hasMatchingRole = false;
                if (is_array($userAzureRoles)) {
                    foreach ($userAzureRoles as $userAzureRole) {
                        if (in_array($userAzureRole, $allowedRoles)) {
                            $hasMatchingRole = true;
                            break;
                        }
                    }
                }
                if (!$hasMatchingRole) {
                    return false;
                }
            }
        }
        
        // Check target_groups (backward compatibility with old role system)
        $targetGroups = json_decode($poll['target_groups'], true);
        if (!in_array($userRole, $targetGroups)) {
            return false;
        }
        
        // For internal polls, hide if user has already voted
        if (!empty($poll['is_internal']) && !empty($poll['user_has_voted']) && $poll['user_has_voted'] > 0) {
            return false;
        }
        
        return true;
    });
}

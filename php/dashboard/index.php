<?php

require_once __DIR__ . '/../config/config.php';

json_response([
    'active_rfqs' => 0,
    'pending_approvals' => 0,
    'monthly_spend' => 0,
    'active_vendors' => 0,
    'recent_rfqs' => [],
    'recent_activity' => [],
    'recent_pos' => []
]);
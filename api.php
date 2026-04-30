<?php
/**
 * Multi-Tenant Shaver API
 *
 * REST API for domain management, session management, tracking, and analytics
 */

require_once __DIR__ . '/config.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

if ($method === 'OPTIONS') {
    exit(0);
}

// Read POST body once and store globally so functions don't re-read php://input
$GLOBALS['_POST_DATA'] = null;
$request = isset($_GET['action']) ? $_GET['action'] : '';
if ($method === 'POST') {
    $GLOBALS['_POST_DATA'] = json_decode(file_get_contents('php://input'), true);
    if (empty($request)) {
        $request = $GLOBALS['_POST_DATA']['action'] ?? '';
    }
}

// Helper to get POST data (avoids re-reading php://input)
function getPostData() {
    return $GLOBALS['_POST_DATA'] ?? [];
}

try {
    switch ($request) {
        // Domain management
        case 'register_domain':   registerDomain($pdo); break;
        case 'get_domains':       getDomains($pdo); break;
        case 'get_domain':        getDomain($pdo); break;
        case 'update_domain':     updateDomain($pdo); break;
        case 'delete_domain':     deleteDomain($pdo); break;

        // Per-page BuyGoods script config
        case 'get_page_scripts':    getPageScripts($pdo); break;
        case 'save_page_scripts':   savePageScripts($pdo); break;
        case 'delete_page_script':  deletePageScript($pdo); break;

        // Session management
        case 'get_sessions':      getSessions($pdo); break;
        case 'create_session':    createSession($pdo); break;
        case 'stop_session':      stopSession($pdo); break;
        case 'get_aff_pages':     getAffPages($pdo); break;
        case 'get_top_affiliates_7d': getTopAffiliates7d($pdo); break;
        case 'get_history':       getHistory($pdo); break;
        case 'delete_history':    deleteHistory($pdo); break;

        // Tracking
        case 'track_visit':       trackVisit($pdo); break;
        case 'track_click':       trackClick($pdo); break;
        case 'log_traffic':       logTraffic($pdo); break;
        case 'update_sessid2':    updateSessid2($pdo); break;
        case 'log_behavior_event': logBehaviorEvent($pdo); break;
        case 'update_session_metrics': updateSessionMetrics($pdo); break;

        // Analytics
        case 'get_analytics':     getAnalytics($pdo); break;
        case 'get_flowchart_comparison': getFlowchartComparison($pdo); break;
        case 'get_traffic_log':   getTrafficLog($pdo); break;
        case 'get_traffic_chart': getTrafficChart($pdo); break;
        case 'get_behavior_details': getBehaviorDetails($pdo); break;
        case 'get_heatmap_data':     getHeatmapData($pdo); break;
        case 'get_heatmap_pages':    getHeatmapPages($pdo); break;
        case 'get_visitor_session':  getVisitorSession($pdo); break;
        case 'match_traffic_orders': matchTrafficOrders($pdo); break;
        case 'get_dashboard_stats': getDashboardStats($pdo); break;
        case 'check_ip_fraud':    checkIPFraud($pdo); break;
        case 'check_ip_fraud_direct': checkIPFraudDirect($pdo); break;
        case 'get_address_match': getAddressMatch($pdo); break;
        case 'get_address_match_order': getAddressMatchOrder($pdo); break;
        case 'upload_affiliates': uploadAffiliates($pdo); break;
        case 'get_affiliate_names': getAffiliateNames($pdo); break;
        case 'cb_api_data': getCBApiData($pdo); break;
        case 'cb_update_api_key': updateCbApiKey($pdo); break;
        case 'cb_get_api_key_masked': getCbApiKeyMasked($pdo); break;
        case 'cb_get_nicknames': getCbNicknamesEndpoint($pdo); break;
        case 'cb_product_detail': getCbProductDetail($pdo); break;
        case 'cb_product_create': createCbProduct($pdo); break;
        case 'cb_product_delete': deleteCbProduct($pdo); break;
        case 'sync_cb_orders': syncCbOrders($pdo); break;
        case 'delete_test_orders': deleteTestOrders($pdo); break;
        case 'get_checkout_config': getCheckoutConfig($pdo); break;
        case 'get_all_checkout_configs': getAllCheckoutConfigs($pdo); break;
        case 'save_checkout_config': saveCheckoutConfig($pdo); break;
        case 'delete_checkout_config': deleteCheckoutConfig($pdo); break;
        case 'get_affiliate_report': getAffiliateReport($pdo); break;
        case 'get_report_countries': getReportCountries($pdo); break;

        // Orders & Customers
        case 'upload_orders':     uploadOrders($pdo); break;
        case 'get_orders':        getOrders($pdo); break;
        case 'get_order_stats':   getOrderStats($pdo); break;
        case 'get_customers':     getCustomers($pdo); break;
        case 'get_customer_details': getCustomerDetails($pdo); break;

        // Checkout Leads (isolated feature)
        case 'log_checkout_lead':       logCheckoutLead($pdo); break;
        case 'mark_checkout_completed': markCheckoutCompleted($pdo); break;
        case 'get_checkout_leads':      getCheckoutLeads($pdo); break;
        case 'get_checkout_stats':      getCheckoutStats($pdo); break;

        // Tracked Sources
        case 'add_tracked_source':    addTrackedSource($pdo); break;
        case 'remove_tracked_source': removeTrackedSource($pdo); break;
        case 'get_tracked_sources':   getTrackedSources($pdo); break;
        case 'get_order_detail':  getOrderDetail($pdo); break;
        case 'save_order_fulfillment': saveOrderFulfillment($pdo); break;
        case 'update_fulfillment_progress': updateFulfillmentProgress($pdo); break;
        case 'toggle_delay_mail': toggleDelayMail($pdo); break;
        case 'update_smart_state': updateSmartState($pdo); break;
        case 'check_smart_conversion': checkSmartConversion($pdo); break;
        case 'send_delay_mail':    sendDelayMailAction($pdo); break;
        case 'preview_delay_mail': previewDelayMail($pdo); break;
        case 'preview_reorder_mail': previewReorderMail($pdo); break;
        case 'send_reorder_mail':    sendReorderMailAction($pdo); break;
        case 'schedule_reorder_mail': scheduleReorderMailAction($pdo); break;
        case 'get_reorder_history':  getReorderHistory($pdo); break;
        case 'preview_dispatch_mail': previewDispatchMail($pdo); break;
        case 'send_dispatch_mail':    sendDispatchMailAction($pdo); break;
        case 'preview_compensation_mail': previewCompensationMail($pdo); break;
        case 'send_compensation_mail':    sendCompensationMailAction($pdo); break;
        case 'get_notifications':    getNotifications($pdo); break;
        case 'start_reorder_campaign': startReorderCampaign($pdo); break;
        case 'stop_reorder_campaign':  stopReorderCampaign($pdo); break;
        case 'get_reorder_campaign':   getReorderCampaign($pdo); break;

        // Shave Snapshots (before/after cookie comparison)
        case 'log_shave_snapshot':    logShaveSnapshot($pdo); break;
        case 'get_shave_snapshots':   getShaveSnapshots($pdo); break;
        case 'delete_shave_snapshots': deleteShaveSnapshots($pdo); break;

        // Sales & Affiliate Analytics
        case 'get_sales_analytics':   getSalesAnalytics($pdo); break;
        case 'get_affiliate_roi':     getAffiliateRoi($pdo); break;
        case 'get_cb_pixel_hits':     getCbPixelHits($pdo); break;
        case 'get_ipn_events':        getIpnEvents($pdo); break;
        case 'delete_cb_pixel_hit':   deleteCbPixelHit($pdo); break;
        case 'log_copy_attempt':      logCopyAttempt($pdo); break;
        case 'export_traffic':        exportTraffic($pdo); break;

        // VSL Shuffle
        case 'get_vsl_configs':       getVslConfigs($pdo); break;
        case 'save_vsl_config':       saveVslConfig($pdo); break;
        case 'delete_vsl_config':     deleteVslConfig($pdo); break;
        case 'update_vsl_page':       updateVslPage($pdo); break;
        case 'get_vsl_embed':         getVslEmbed($pdo); break;

        // Per-domain shipping (country selector + shipping override)
        case 'get_shipping_config':   getShippingConfig($pdo); break;
        case 'save_shipping_row':     saveShippingRow($pdo); break;
        case 'delete_shipping_row':   deleteShippingRow($pdo); break;

        // Standalone shipping domain registration
        case 'register_shipping_domain': registerShippingDomain($pdo); break;
        case 'get_shipping_domains':     getShippingDomains($pdo); break;
        case 'delete_shipping_domain':   deleteShippingDomain($pdo); break;

        // Auto-detected card config (called by s.js/t.js bootstrap)
        case 'save_card_config':         saveCardConfig($pdo); break;

        // URL-param order capture (BG upsell pages)
        case 'capture_url_order': captureUrlOrder($pdo); break;

        // API key management
        case 'create_api_key':  createApiKey($pdo); break;
        case 'list_api_keys':   listApiKeys($pdo); break;
        case 'revoke_api_key':  revokeApiKey($pdo); break;
        case 'delete_api_key':  deleteApiKey($pdo); break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Invalid endpoint: ' . $request]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// ================================================================
// DOMAIN MANAGEMENT
// ================================================================

function registerDomain($pdo) {
    $data = getPostData();

    $label = trim($data['label'] ?? '');
    $domainUrl = trim($data['domain_url'] ?? '');
    $bgAccountId = trim($data['bg_account_id'] ?? '');
    $bgProductCodes = trim($data['bg_product_codes'] ?? '');
    $bgConversionToken = trim($data['bg_conversion_token'] ?? '');
    $bgTrackingScript = $data['bg_tracking_script'] ?? '';
    $bgIframeScript = $data['bg_iframe_script'] ?? '';
    $platform = trim($data['platform'] ?? 'buygoods');
    $ds24ProductId = trim($data['ds24_product_id'] ?? '');
    $cbVendor = trim($data['cb_vendor'] ?? '');

    if (empty($label) || empty($domainUrl)) {
        http_response_code(400);
        echo json_encode(['error' => 'Label and domain URL are required']);
        return;
    }

    if ($platform === 'buygoods' && empty($bgTrackingScript)) {
        http_response_code(400);
        echo json_encode(['error' => 'BuyGoods tracking script is required']);
        return;
    }

    if ($platform === 'digistore24' && empty($bgTrackingScript)) {
        http_response_code(400);
        echo json_encode(['error' => 'Digistore24 tracking script is required']);
        return;
    }

    if ($platform === 'clickbank' && empty($bgTrackingScript)) {
        http_response_code(400);
        echo json_encode(['error' => 'ClickBank tracking script is required']);
        return;
    }

    // Generate domain_key from URL
    $domainKey = generateDomainKey($domainUrl);

    // Check for duplicate - allow re-registration if previously deleted
    $stmt = $pdo->prepare("SELECT id, status FROM domains WHERE domain_key = ?");
    $stmt->execute([$domainKey]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($existing['status'] === 'deleted') {
            // Hard-delete the old record so we can re-register
            try { $pdo->prepare("DELETE FROM domain_page_scripts WHERE domain_id = ?")->execute([$existing['id']]); } catch (Exception $e) {}
            $pdo->prepare("DELETE FROM domains WHERE id = ?")->execute([$existing['id']]);
        } else {
            http_response_code(409);
            echo json_encode(['error' => 'Domain already registered with key: ' . $domainKey]);
            return;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO domains (domain_key, domain_url, label, bg_account_id, bg_product_codes, bg_conversion_token, bg_tracking_script, bg_iframe_script, platform, ds24_product_id, cb_vendor)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$domainKey, $domainUrl, $label, $bgAccountId, $bgProductCodes, $bgConversionToken, $bgTrackingScript, $bgIframeScript, $platform, $ds24ProductId, $cbVendor]);
    $newDomainId = (int)$pdo->lastInsertId();

    // Optionally save page-specific BG scripts (upsell1, upsell2, etc.)
    if ($platform === 'buygoods' && !empty($data['page_scripts']) && is_array($data['page_scripts'])) {
        insertPageScripts($pdo, $newDomainId, $data['page_scripts']);
    }

    echo json_encode([
        'success' => true,
        'domain_id' => $newDomainId,
        'domain_key' => $domainKey
    ]);
}

/**
 * Insert page script rows for a domain. Caller is responsible for clearing
 * previous rows first if this is a "replace all" operation.
 */
function insertPageScripts($pdo, $domainId, $rows) {
    $ins = $pdo->prepare("
        INSERT INTO domain_page_scripts
            (domain_id, page_label, url_path_pattern, bg_product_codes, is_upsell, bg_raw_script, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $order = 0;
    foreach ($rows as $row) {
        $pattern = trim($row['url_path_pattern'] ?? '');
        $codes = trim($row['bg_product_codes'] ?? '');
        if ($pattern === '' || $codes === '') continue;
        $ins->execute([
            $domainId,
            trim($row['page_label'] ?? ''),
            $pattern,
            $codes,
            !empty($row['is_upsell']) ? 1 : 0,
            $row['bg_raw_script'] ?? null,
            $order++
        ]);
    }
}

function getDomains($pdo) {
    $stmt = $pdo->query("SELECT id, domain_key, domain_url, label, bg_account_id, bg_product_codes, bg_conversion_token, bg_tracking_script, platform, ds24_product_id, cb_vendor, status, created_at FROM domains WHERE status != 'deleted' ORDER BY created_at DESC");
    $domains = $stmt->fetchAll();

    // Attach per-page BG scripts (only if table exists — safe on un-migrated installs)
    try {
        $scriptsByDomain = [];
        $psStmt = $pdo->query("SELECT id, domain_id, page_label, url_path_pattern, bg_product_codes, is_upsell, sort_order FROM domain_page_scripts ORDER BY domain_id, sort_order, id");
        foreach ($psStmt->fetchAll() as $ps) {
            $ps['is_upsell'] = (int)$ps['is_upsell'];
            $scriptsByDomain[(int)$ps['domain_id']][] = $ps;
        }
        foreach ($domains as &$d) {
            $d['page_scripts'] = $scriptsByDomain[(int)$d['id']] ?? [];
        }
        unset($d);
    } catch (Exception $e) {
        // Table doesn't exist yet — migration not run. Return domains without page_scripts.
    }

    echo json_encode(['success' => true, 'domains' => $domains]);
}

// ================================================================
// PER-PAGE BG SCRIPTS (for upsell1, upsell2, etc.)
// ================================================================

function getPageScripts($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? $_GET['domain_id'] ?? 0);

    if ($domainId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'domain_id required']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT id, domain_id, page_label, url_path_pattern, bg_product_codes, is_upsell, bg_raw_script, sort_order, created_at
        FROM domain_page_scripts
        WHERE domain_id = ?
        ORDER BY sort_order, id
    ");
    $stmt->execute([$domainId]);
    $scripts = $stmt->fetchAll();
    foreach ($scripts as &$s) { $s['is_upsell'] = (int)$s['is_upsell']; }
    unset($s);

    echo json_encode(['success' => true, 'scripts' => $scripts]);
}

/**
 * Replace all page scripts for a domain with the provided array.
 * Called from the setup.html UI "Save Page Scripts" button.
 */
function savePageScripts($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $rows = $data['scripts'] ?? [];

    if ($domainId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'domain_id required']);
        return;
    }

    // Verify domain exists
    $chk = $pdo->prepare("SELECT id FROM domains WHERE id = ? AND status != 'deleted'");
    $chk->execute([$domainId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Domain not found']);
        return;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM domain_page_scripts WHERE domain_id = ?")->execute([$domainId]);
        if (is_array($rows) && count($rows) > 0) {
            insertPageScripts($pdo, $domainId, $rows);
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Save failed: ' . $e->getMessage()]);
        return;
    }

    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM domain_page_scripts WHERE domain_id = " . $domainId)->fetchColumn();
    echo json_encode(['success' => true, 'count' => $cnt]);
}

function deletePageScript($pdo) {
    $data = getPostData();
    $scriptId = (int)($data['script_id'] ?? 0);

    if ($scriptId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'script_id required']);
        return;
    }

    $pdo->prepare("DELETE FROM domain_page_scripts WHERE id = ?")->execute([$scriptId]);
    echo json_encode(['success' => true]);
}

function getDomain($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $domainKey = $data['domain_key'] ?? $_GET['domain_key'] ?? '';

    if (!empty($domainId)) {
        $stmt = $pdo->prepare("SELECT * FROM domains WHERE id = ?");
        $stmt->execute([$domainId]);
    } elseif (!empty($domainKey)) {
        $stmt = $pdo->prepare("SELECT * FROM domains WHERE domain_key = ?");
        $stmt->execute([$domainKey]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'domain_id or domain_key required']);
        return;
    }

    $domain = $stmt->fetch();
    if (!$domain) {
        http_response_code(404);
        echo json_encode(['error' => 'Domain not found']);
        return;
    }

    echo json_encode(['success' => true, 'domain' => $domain]);
}

function updateDomain($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? '';

    if (empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'domain_id required']);
        return;
    }

    $fields = [];
    $params = [];

    if (isset($data['label'])) { $fields[] = 'label = ?'; $params[] = $data['label']; }
    if (isset($data['domain_url'])) { $fields[] = 'domain_url = ?'; $params[] = $data['domain_url']; }
    if (isset($data['bg_account_id'])) { $fields[] = 'bg_account_id = ?'; $params[] = $data['bg_account_id']; }
    if (isset($data['bg_product_codes'])) { $fields[] = 'bg_product_codes = ?'; $params[] = $data['bg_product_codes']; }
    if (isset($data['bg_conversion_token'])) { $fields[] = 'bg_conversion_token = ?'; $params[] = $data['bg_conversion_token']; }
    if (isset($data['bg_tracking_script'])) { $fields[] = 'bg_tracking_script = ?'; $params[] = $data['bg_tracking_script']; }
    if (isset($data['bg_iframe_script'])) { $fields[] = 'bg_iframe_script = ?'; $params[] = $data['bg_iframe_script']; }
    if (isset($data['platform'])) { $fields[] = 'platform = ?'; $params[] = $data['platform']; }
    if (isset($data['ds24_product_id'])) { $fields[] = 'ds24_product_id = ?'; $params[] = $data['ds24_product_id']; }
    if (isset($data['cb_vendor'])) { $fields[] = 'cb_vendor = ?'; $params[] = $data['cb_vendor']; }
    if (isset($data['status'])) { $fields[] = 'status = ?'; $params[] = $data['status']; }

    if (empty($fields)) {
        echo json_encode(['success' => true, 'message' => 'Nothing to update']);
        return;
    }

    $params[] = $domainId;
    $sql = "UPDATE domains SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);
}

function deleteDomain($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? '';

    if (empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'domain_id required']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE domains SET status = 'deleted' WHERE id = ?");
    $stmt->execute([$domainId]);

    // Also stop all active sessions for this domain
    $stmt = $pdo->prepare("UPDATE shaving_sessions SET active = 0, stop_time = NOW() WHERE domain_id = ? AND active = 1");
    $stmt->execute([$domainId]);

    echo json_encode(['success' => true]);
}

// ================================================================
// SESSION MANAGEMENT
// ================================================================

function getSessions($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';

    $sql = "
        SELECT s.*, n.name as aff_name,
            COALESCE(SUM(CASE WHEN t.event_type = 'visit' THEN 1 ELSE 0 END), 0) as visits,
            COALESCE(SUM(CASE WHEN t.event_type = 'click' THEN 1 ELSE 0 END), 0) as clicks,
            COALESCE(at_stats.traffic_count, 0) as traffic_shaved,
            COALESCE(at_stats.smart_safe_count, 0) as traffic_safe,
            COALESCE(at_stats.order_count, 0) as traffic_orders,
            (SELECT COUNT(*) FROM affiliate_traffic at2
             WHERE at2.domain_id = s.domain_id
             AND LOWER(at2.aff_id) = LOWER(s.aff_id)
             AND at2.page_type = 'landing'
             AND at2.timestamp >= s.start_time) as traffic_total
        FROM shaving_sessions s
        LEFT JOIN affiliate_names n ON n.domain_id = s.domain_id AND n.aff_id = s.aff_id
        LEFT JOIN shaving_tracking t ON s.id = t.session_id
        LEFT JOIN (
            SELECT shaving_session_id,
                SUM(CASE WHEN was_shaved = 1 THEN 1 ELSE 0 END) as traffic_count,
                SUM(CASE WHEN smart_skipped = 1 THEN 1 ELSE 0 END) as smart_safe_count,
                SUM(CASE WHEN matched_order_id IS NOT NULL AND matched_order_id != '' THEN 1 ELSE 0 END) as order_count
            FROM affiliate_traffic
            WHERE shaving_session_id IS NOT NULL
            GROUP BY shaving_session_id
        ) at_stats ON s.id = at_stats.shaving_session_id
        WHERE s.active = 1
    ";
    $params = [];

    if (!empty($domainId)) {
        $sql .= " AND s.domain_id = ?";
        $params[] = $domainId;
    }

    $sql .= " GROUP BY s.id ORDER BY s.start_time DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();

    $formatted = array_map(function($s) {
        return [
            'id' => $s['id'],
            'domainId' => (int)$s['domain_id'],
            'affId' => $s['aff_id'],
            'affName' => $s['aff_name'] ?? '',
            'subId' => $s['sub_id'],
            'mode' => $s['mode'],
            'replaceAffId' => $s['replace_aff_id'],
            'replaceSubId' => $s['replace_sub_id'],
            'cbPathFind' => $s['cb_path_find'] ?? '',
            'cbPathReplace' => $s['cb_path_replace'] ?? '',
            'shaveMode' => $s['shave_mode'] ?? 'instant',
            'smartSkipNext' => (bool)($s['smart_skip_next'] ?? 0),
            'smartLastTrafficId' => $s['smart_last_traffic_id'] ?? null,
            'startTime' => strtotime($s['start_time']) * 1000,
            'active' => (bool)$s['active'],
            'notes' => $s['notes'],
            'visits' => (int)$s['visits'],
            'clicks' => (int)$s['clicks'],
            'trafficShaved' => (int)$s['traffic_shaved'],
            'trafficSafe' => (int)($s['traffic_safe'] ?? 0),
            'trafficOrders' => (int)($s['traffic_orders'] ?? 0),
            'trafficTotal' => (int)$s['traffic_total']
        ];
    }, $sessions);

    echo json_encode(['success' => true, 'data' => $formatted]);
}

function createSession($pdo) {
    $data = getPostData();

    $domainId = $data['domain_id'] ?? '';
    $affId = $data['aff_id'] ?? '';
    $subId = $data['sub_id'] ?? '';
    $mode = $data['mode'] ?? 'remove';
    if (!in_array($mode, ['remove', 'replace', 'cb_replace'])) $mode = 'remove';
    $replaceAffId = $data['replace_aff_id'] ?? '';
    $replaceSubId = $data['replace_sub_id'] ?? '';
    $cbPathFind = $data['cb_path_find'] ?? '';
    $cbPathReplace = $data['cb_path_replace'] ?? '';
    $notes = $data['notes'] ?? '';
    $shaveMode = $data['shave_mode'] ?? 'instant'; // 'instant' or 'smart'
    if (!in_array($shaveMode, ['instant', 'smart'])) $shaveMode = 'instant';
    $id = uniqid('session_', true);

    if (empty($domainId) || empty($affId)) {
        http_response_code(400);
        echo json_encode(['error' => 'domain_id and aff_id are required']);
        return;
    }

    // Check duplicate - simplified to avoid collation mismatch
    if (empty($subId)) {
        $stmt = $pdo->prepare("SELECT id FROM shaving_sessions WHERE domain_id = ? AND aff_id = ? AND (sub_id IS NULL OR sub_id = '') AND active = 1");
        $stmt->execute([$domainId, $affId]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM shaving_sessions WHERE domain_id = ? AND aff_id = ? AND sub_id = ? AND active = 1");
        $stmt->execute([$domainId, $affId, $subId]);
    }
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Active session already exists for this affiliate on this domain']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO shaving_sessions (id, domain_id, aff_id, sub_id, mode, shave_mode, replace_aff_id, replace_sub_id, cb_path_find, cb_path_replace, notes, start_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$id, $domainId, $affId, $subId ?: null, $mode, $shaveMode, $replaceAffId ?: null, $replaceSubId ?: null, $cbPathFind ?: null, $cbPathReplace ?: null, $notes ?: null]);

    echo json_encode(['success' => true, 'sessionId' => $id]);
}

function stopSession($pdo) {
    $data = getPostData();
    $sessionId = $data['session_id'] ?? '';

    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM shaving_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
        return;
    }

    // Count visits/clicks
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN event_type = 'visit' THEN 1 ELSE 0 END) as visits,
            SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) as clicks
        FROM shaving_tracking WHERE session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $stats = $stmt->fetch();

    $stopTime = date('Y-m-d H:i:s');
    $duration = time() - strtotime($session['start_time']);

    // Archive to history
    $stmt = $pdo->prepare("
        INSERT INTO shaving_history (session_id, domain_id, aff_id, sub_id, mode, shave_mode, replace_aff_id, replace_sub_id, start_time, stop_time, total_visits, total_clicks, duration, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId, $session['domain_id'], $session['aff_id'], $session['sub_id'],
        $session['mode'], $session['shave_mode'] ?? 'instant',
        $session['replace_aff_id'], $session['replace_sub_id'],
        $session['start_time'], $stopTime,
        $stats['visits'] ?? 0, $stats['clicks'] ?? 0, $duration, $session['notes']
    ]);

    // Deactivate
    $stmt = $pdo->prepare("UPDATE shaving_sessions SET active = 0, stop_time = NOW() WHERE id = ?");
    $stmt->execute([$sessionId]);

    echo json_encode(['success' => true]);
}

function getTopAffiliates7d($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    if (!$domainId) { echo json_encode(['success' => false]); return; }

    $stmt = $pdo->prepare("
        SELECT t.aff_id, COUNT(*) as visits, n.name,
            COALESCE(o.order_count, 0) as orders_2m
        FROM affiliate_traffic t
        LEFT JOIN affiliate_names n ON n.domain_id = t.domain_id AND n.aff_id = t.aff_id
        LEFT JOIN (
            SELECT affiliate_id, domain_id, COUNT(*) as order_count
            FROM orders
            WHERE domain_id = ? AND date_created >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
            GROUP BY affiliate_id, domain_id
        ) o ON o.affiliate_id = t.aff_id AND o.domain_id = t.domain_id
        WHERE t.domain_id = ? AND t.aff_id IS NOT NULL AND t.aff_id != ''
          AND t.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND t.page_type = 'landing'
        GROUP BY t.aff_id, n.name, o.order_count
        ORDER BY visits DESC
        LIMIT 30
    ");
    $stmt->execute([$domainId, $domainId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'affiliates' => $rows]);
}

function getAffPages($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $affId = $data['aff_id'] ?? '';

    if (!$domainId || !$affId) {
        echo json_encode(['success' => false, 'error' => 'domain_id and aff_id required']);
        return;
    }

    // Get the most-used page URLs for this affiliate on this domain, with the actual params they used
    $stmt = $pdo->prepare("
        SELECT page_url as url, page_type, COUNT(*) as count
        FROM affiliate_traffic
        WHERE domain_id = ? AND LOWER(aff_id) = LOWER(?) AND page_url IS NOT NULL AND page_url != '' AND (page_type IS NULL OR page_type = 'landing')
        GROUP BY page_url, page_type
        ORDER BY count DESC
        LIMIT 15
    ");
    $stmt->execute([$domainId, $affId]);
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'pages' => $pages]);
}

function getHistory($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';

    $sql = "SELECT * FROM shaving_history";
    $params = [];

    if (!empty($domainId)) {
        $sql .= " WHERE domain_id = ?";
        $params[] = $domainId;
    }

    $sql .= " ORDER BY stop_time DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history = $stmt->fetchAll();

    $formatted = array_map(function($item) {
        return [
            'id' => $item['session_id'],
            'domainId' => (int)$item['domain_id'],
            'affId' => $item['aff_id'],
            'subId' => $item['sub_id'],
            'mode' => $item['mode'],
            'replaceAffId' => $item['replace_aff_id'],
            'replaceSubId' => $item['replace_sub_id'],
            'startTime' => strtotime($item['start_time']) * 1000,
            'stopTime' => strtotime($item['stop_time']) * 1000,
            'visits' => (int)$item['total_visits'],
            'clicks' => (int)$item['total_clicks'],
            'duration' => (int)$item['duration'],
            'notes' => $item['notes']
        ];
    }, $history);

    echo json_encode(['success' => true, 'data' => $formatted]);
}

function deleteHistory($pdo) {
    $data = getPostData();
    $sessionId = $data['session_id'] ?? '';

    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id required']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM shaving_history WHERE session_id = ?");
    $stmt->execute([$sessionId]);

    echo json_encode(['success' => true]);
}

// ================================================================
// SMART SHAVING ENDPOINTS
// ================================================================

/**
 * Update smart shaving state after a visitor is shaved or skipped.
 * Called from check.php JS after the shaving decision.
 */
function updateSmartState($pdo) {
    $data = getPostData();
    $sessionId = $data['session_id'] ?? '';
    $action = $data['smart_action'] ?? ''; // 'shaved' or 'skipped'
    $trafficId = $data['traffic_id'] ?? null;

    if (empty($sessionId) || empty($action)) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id and smart_action required']);
        return;
    }

    if ($action === 'shaved') {
        // Visitor was shaved — set skip_next=1 and record their traffic_id
        $stmt = $pdo->prepare("UPDATE shaving_sessions SET smart_skip_next = 1, smart_last_traffic_id = ? WHERE id = ? AND active = 1");
        $stmt->execute([$trafficId, $sessionId]);
    } elseif ($action === 'skipped') {
        // Visitor was skipped (safe visit) — reset to shave_next
        $stmt = $pdo->prepare("UPDATE shaving_sessions SET smart_skip_next = 0 WHERE id = ? AND active = 1");
        $stmt->execute([$sessionId]);
    }

    echo json_encode(['success' => true]);
}

/**
 * Check if the last shaved visitor converted (reached upsell or thankyou).
 * Called from check.php PHP when building JS for smart sessions.
 * Also available as an API endpoint for client-side checks.
 */
function checkSmartConversion($pdo) {
    $data = getPostData();
    $sessionId = $data['session_id'] ?? '';

    if (empty($sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'session_id required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT smart_last_traffic_id FROM shaving_sessions WHERE id = ? AND active = 1");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session || !$session['smart_last_traffic_id']) {
        echo json_encode(['success' => true, 'converted' => false, 'reason' => 'no_previous']);
        return;
    }

    $converted = _checkTrafficConverted($pdo, $session['smart_last_traffic_id']);
    echo json_encode(['success' => true, 'converted' => $converted]);
}

/**
 * Internal helper: check if a traffic record's session reached upsell/thankyou.
 */
function _checkTrafficConverted($pdo, $trafficId) {
    // Get the session_uuid of the last shaved visitor
    $stmt = $pdo->prepare("SELECT session_uuid, domain_id FROM affiliate_traffic WHERE id = ?");
    $stmt->execute([$trafficId]);
    $traffic = $stmt->fetch();

    if (!$traffic || !$traffic['session_uuid']) return false;

    // Check if that same session_uuid has upsell or thankyou page visits
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM affiliate_traffic
        WHERE session_uuid = ? AND domain_id = ? AND page_type IN ('upsell', 'thankyou')
    ");
    $stmt->execute([$traffic['session_uuid'], $traffic['domain_id']]);
    return $stmt->fetchColumn() > 0;
}

// ================================================================
// TRACKING ENDPOINTS
// ================================================================

function trackVisit($pdo) {
    $data = getPostData();

    $sessionId = $data['session_id'] ?? '';
    $domainId = $data['domain_id'] ?? 0;
    $affId = $data['aff_id'] ?? '';
    $subId = $data['sub_id'] ?? '';
    $page = $data['page'] ?? '';
    $referrer = $data['referrer'] ?? '';

    $stmt = $pdo->prepare("
        INSERT INTO shaving_tracking (session_id, domain_id, aff_id, sub_id, event_type, page, referrer, timestamp)
        VALUES (?, ?, ?, ?, 'visit', ?, ?, NOW())
    ");
    $stmt->execute([$sessionId, $domainId, $affId, $subId, $page, $referrer]);

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shaving_tracking WHERE session_id = ? AND event_type = 'visit'");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch();

    echo json_encode(['success' => true, 'totalVisits' => (int)$result['total']]);
}

function trackClick($pdo) {
    $data = getPostData();

    $sessionId = $data['session_id'] ?? '';
    $domainId = $data['domain_id'] ?? 0;
    $affId = $data['aff_id'] ?? '';
    $subId = $data['sub_id'] ?? '';
    $page = $data['page'] ?? '';

    $stmt = $pdo->prepare("
        INSERT INTO shaving_tracking (session_id, domain_id, aff_id, sub_id, event_type, page, timestamp)
        VALUES (?, ?, ?, ?, 'click', ?, NOW())
    ");
    $stmt->execute([$sessionId, $domainId, $affId, $subId, $page]);

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shaving_tracking WHERE session_id = ? AND event_type = 'click'");
    $stmt->execute([$sessionId]);
    $result = $stmt->fetch();

    echo json_encode(['success' => true, 'totalClicks' => (int)$result['total']]);
}

function logTraffic($pdo) {
    $data = getPostData();

    $domainId = $data['domain_id'] ?? 0;
    $affId = $data['aff_id'] ?? '';
    $subId = $data['sub_id'] ?? '';
    $pageUrl = $data['page_url'] ?? '';
    $referrer = $data['referrer'] ?? '';
    $userAgent = $data['user_agent'] ?? '';
    $wasShaved = $data['was_shaved'] ?? false;
    $smartSkipped = $data['smart_skipped'] ?? false;
    $shavingSessionId = $data['shaving_session_id'] ?? null;
    $sessionUUID = $data['session_uuid'] ?? null;
    $screenWidth = $data['screen_width'] ?? null;
    $screenHeight = $data['screen_height'] ?? null;
    $viewportWidth = $data['viewport_width'] ?? null;
    $viewportHeight = $data['viewport_height'] ?? null;
    $isBot = $data['is_bot'] ?? 0;
    $botFlags = $data['bot_flags'] ?? null;
    $isIframe = $data['is_iframe'] ?? 0;
    $pageType = $data['page_type'] ?? 'landing';
    $sessid2 = $data['sessid2'] ?? null;
    $cbParams = $data['cb_params'] ?? null;

    // Validate page_type
    if (!in_array($pageType, ['landing', 'upsell', 'thankyou'])) $pageType = 'landing';


    // Deduplicate: same session_uuid + domain + page_type = same record
    if (!empty($sessionUUID)) {
        $dedup = $pdo->prepare("SELECT id FROM affiliate_traffic WHERE session_uuid = ? AND domain_id = ? AND page_type = ? LIMIT 1");
        $dedup->execute([$sessionUUID, $domainId, $pageType]);
        $existing = $dedup->fetch();
        if ($existing) {
            echo json_encode(['success' => true, 'traffic_id' => $existing['id']]);
            return;
        }
    }

    $ip = getClientIP();
    $browserInfo = parseBrowserInfo($userAgent);

    // INSERT immediately WITHOUT GeoIP to return traffic_id fast
    $stmt = $pdo->prepare("
        INSERT INTO affiliate_traffic
        (domain_id, aff_id, sub_id, page_url, page_type, sessid2, cb_params, referrer, user_agent, browser, device, ip_address,
         was_shaved, smart_skipped, shaving_session_id, session_uuid, screen_width, screen_height, viewport_width, viewport_height,
         is_bot, bot_flags, is_iframe)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $domainId, $affId, $subId, $pageUrl, $pageType, $sessid2,
        $cbParams ? json_encode($cbParams) : null,
        $referrer, $userAgent,
        $browserInfo['browser'], $browserInfo['device'],
        $ip,
        $wasShaved ? 1 : 0, $smartSkipped ? 1 : 0, $shavingSessionId, $sessionUUID,
        $screenWidth, $screenHeight, $viewportWidth, $viewportHeight,
        $isBot ? 1 : 0, $botFlags, $isIframe ? 1 : 0
    ]);

    $trafficId = $pdo->lastInsertId();

    // Return traffic_id immediately so JS tracking starts right away
    $response = json_encode(['success' => true, 'traffic_id' => $trafficId]);

    // Flush response to client, then do GeoIP lookup in background
    if (function_exists('fastcgi_finish_request')) {
        echo $response;
        fastcgi_finish_request();
    } else {
        ignore_user_abort(true);
        ob_start();
        echo $response;
        $size = ob_get_length();
        header("Content-Length: $size");
        header('Connection: close');
        ob_end_flush();
        flush();
    }

    // Now do GeoIP lookup without blocking the client
    $geoInfo = getGeoInfo($ip);
    if ($geoInfo['country'] !== 'Unknown' || $geoInfo['countryCode'] !== 'XX') {
        $geoStmt = $pdo->prepare("UPDATE affiliate_traffic SET country = ?, country_code = ? WHERE id = ?");
        $geoStmt->execute([$geoInfo['country'], $geoInfo['countryCode'], $trafficId]);
    }

    // Auto-check fraud when visitor reaches upsell
    if ($pageType === 'upsell') {
        $landingRec = null;
        // Try sessid2 first
        if (!empty($sessid2)) {
            $landingStmt = $pdo->prepare("SELECT id, ip_address, fraud_score FROM affiliate_traffic WHERE sessid2 = ? AND page_type = 'landing' AND fraud_score IS NULL LIMIT 1");
            $landingStmt->execute([$sessid2]);
            $landingRec = $landingStmt->fetch();
        }
        // Fallback: use session_uuid
        if (!$landingRec && !empty($sessionUUID)) {
            $landingStmt = $pdo->prepare("SELECT id, ip_address, fraud_score FROM affiliate_traffic WHERE session_uuid = ? AND page_type = 'landing' AND fraud_score IS NULL LIMIT 1");
            $landingStmt->execute([$sessionUUID]);
            $landingRec = $landingStmt->fetch();
        }
        if ($landingRec && $landingRec['ip_address']) {
            $ipAddr = $landingRec['ip_address'];
            $fScore = null; $fRisk = null; $fFlags = null;
            // Check cache first
            $cacheStmt = $pdo->prepare("SELECT fraud_score, fraud_risk_level, fraud_flags FROM affiliate_traffic WHERE ip_address = ? AND fraud_score IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
            $cacheStmt->execute([$ipAddr]);
            $cached = $cacheStmt->fetch();
            if ($cached) {
                $fScore = $cached['fraud_score']; $fRisk = $cached['fraud_risk_level']; $fFlags = $cached['fraud_flags'];
                // Also copy ipqs_raw if available
                $rawCopy = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
                $rawCopy->execute([$ipAddr]);
                $rawCopyRow = $rawCopy->fetch();
                $ipqsRawJson = $rawCopyRow ? $rawCopyRow['ipqs_raw'] : null;
            } elseif (canCallIPQS($pdo)) {
                require_once __DIR__ . '/ipqs.php';
                $ipqs = new IPQS(IPQS_API_KEYS);
                $result = $ipqs->analyzeIP($ipAddr);
                $storageData = $ipqs->getStorageData($result);
                $fScore = $storageData['fraud_score']; $fRisk = $storageData['fraud_risk_level']; $fFlags = $storageData['fraud_flags'];
                $ipqsRawJson = json_encode($result);
                incrementIPQSCounter($pdo);
            }
            if ($fScore !== null) {
                $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ?, ipqs_raw = COALESCE(ipqs_raw, ?) WHERE id = ?");
                $upd->execute([$fScore, $fRisk, $fFlags, $ipqsRawJson ?? null, $landingRec['id']]);
            }
        }
    }
}

/**
 * Update sessid2 on an existing traffic row (called after BuyGoods sets the cookie).
 * Fixes the race condition where logTraffic runs before BG creates sessid2.
 */
function updateSessid2($pdo) {
    $data = getPostData();
    $trafficId = $data['traffic_id'] ?? null;
    $sessid2 = $data['sessid2'] ?? null;
    $sessionUUID = $data['session_uuid'] ?? null;

    if (empty($sessid2)) {
        echo json_encode(['success' => false, 'error' => 'No sessid2 provided']);
        return;
    }

    // Update by traffic_id (preferred) or session_uuid fallback
    if (!empty($trafficId)) {
        $stmt = $pdo->prepare("UPDATE affiliate_traffic SET sessid2 = ? WHERE id = ? AND (sessid2 IS NULL OR sessid2 = '')");
        $stmt->execute([$sessid2, $trafficId]);
    } elseif (!empty($sessionUUID)) {
        $stmt = $pdo->prepare("UPDATE affiliate_traffic SET sessid2 = ? WHERE session_uuid = ? AND (sessid2 IS NULL OR sessid2 = '')");
        $stmt->execute([$sessid2, $sessionUUID]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No traffic_id or session_uuid']);
        return;
    }

    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
}

function logBehaviorEvent($pdo) {
    $data = getPostData();

    $domainId = $data['domain_id'] ?? 0;
    $trafficId = $data['traffic_id'] ?? null;
    $sessionUUID = $data['session_uuid'] ?? null;
    $eventType = $data['event_type'] ?? '';
    $eventData = $data['event_data'] ?? [];
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');

    if (empty($trafficId) || empty($eventType)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $validEvents = ['page_view', 'scroll', 'click', 'hover', 'checkout_reached', 'tab_hidden', 'tab_visible'];
    if (!in_array($eventType, $validEvents)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid event type']);
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO user_behavior_events (traffic_id, domain_id, session_uuid, event_type, event_data, timestamp)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$trafficId, $domainId, $sessionUUID, $eventType, json_encode($eventData), $timestamp]);

    echo json_encode(['success' => true, 'event_id' => $pdo->lastInsertId()]);
}

function updateSessionMetrics($pdo) {
    $data = getPostData();

    $trafficId = $data['traffic_id'] ?? null;
    if (empty($trafficId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing traffic_id']);
        return;
    }

    // Fraud check now happens when visitor reaches upsell (in logTraffic)
    $fraudScore = null;
    $fraudRiskLevel = null;
    $fraudFlags = null;
    $reachedCheckout = $data['reached_checkout'] ?? 0;
    $hasAdblock = isset($data['has_adblock']) && $data['has_adblock'] !== null ? (int)$data['has_adblock'] : null;
    $jsErrorCount = (int)($data['js_error_count'] ?? 0);
    $jsErrors = $data['js_errors'] ?? null;

    $redirectClicks = (int)($data['redirect_clicks'] ?? 0);
    $buynowClicks = (int)($data['buynow_clicks'] ?? 0);
    $footerClicks = (int)($data['footer_clicks'] ?? 0);
    $videoPlays = (int)($data['video_plays'] ?? 0);
    $videoWatchTime = isset($data['video_watch_time']) ? (int)$data['video_watch_time'] : null;
    $magicalRevealed = (int)($data['magical_revealed'] ?? 0);
    $ctaBarClicks = (int)($data['cta_bar_clicks'] ?? 0);
    $vslClicks = (int)($data['vsl_clicks'] ?? 0);
    $clickPositions = $data['click_positions'] ?? null;
    $preRedirectEngagement = $data['pre_redirect_engagement'] ?? null;
    $redirectTimeMs = $data['redirect_time_ms'] ?? null;

    $stmt = $pdo->prepare("
        UPDATE affiliate_traffic SET
            session_duration = ?, max_scroll_depth = ?, total_clicks = ?,
            redirect_clicks = ?, buynow_clicks = ?, footer_clicks = ?,
            video_plays = ?, video_watch_time = GREATEST(COALESCE(video_watch_time, 0), COALESCE(?, 0)),
            magical_revealed = GREATEST(COALESCE(magical_revealed, 0), ?),
            cta_bar_clicks = ?, vsl_clicks = ?,
            click_positions = COALESCE(?, click_positions),
            reached_checkout = ?, checkout_url = ?,
            time_to_first_click = ?, time_to_checkout = ?,
            screen_width = ?, screen_height = ?,
            viewport_width = ?, viewport_height = ?,
            page_load_time = ?, bounce = ?,
            redirect_time_ms = COALESCE(?, redirect_time_ms),
            pre_redirect_engagement = COALESCE(?, pre_redirect_engagement),
            fraud_score = COALESCE(?, fraud_score),
            fraud_risk_level = COALESCE(?, fraud_risk_level),
            fraud_flags = COALESCE(?, fraud_flags),
            has_adblock = COALESCE(?, has_adblock),
            js_error_count = GREATEST(?, js_error_count),
            js_errors = COALESCE(?, js_errors)
        WHERE id = ?
    ");
    $stmt->execute([
        $data['session_duration'] ?? null,
        $data['max_scroll_depth'] ?? 0,
        $data['total_clicks'] ?? 0,
        $redirectClicks,
        $buynowClicks,
        $footerClicks,
        $videoPlays,
        $videoWatchTime,
        $magicalRevealed,
        $ctaBarClicks,
        $vslClicks,
        $clickPositions,
        $reachedCheckout,
        $data['checkout_url'] ?? null,
        $data['time_to_first_click'] ?? null,
        $data['time_to_checkout'] ?? null,
        $data['screen_width'] ?? null,
        $data['screen_height'] ?? null,
        $data['viewport_width'] ?? null,
        $data['viewport_height'] ?? null,
        $data['page_load_time'] ?? null,
        $data['bounce'] ?? 1,
        $redirectTimeMs,
        $preRedirectEngagement,
        $fraudScore,
        $fraudRiskLevel,
        $fraudFlags,
        $hasAdblock,
        $jsErrorCount,
        $jsErrors,
        $trafficId
    ]);

    echo json_encode(['success' => true]);
}

function checkIPFraud($pdo) {
    $data = getPostData();
    $trafficId = $data['traffic_id'] ?? null;
    $force = !empty($data['force']);

    if (empty($trafficId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing traffic_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT ip_address, fraud_score FROM affiliate_traffic WHERE id = ?");
    $stmt->execute([$trafficId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Traffic record not found']);
        return;
    }

    $ip = $row['ip_address'];

    // Check if already scored via cache (any record with this IP) — skip if force
    $cached = null;
    if (!$force) {
        $cacheStmt = $pdo->prepare("SELECT fraud_score, fraud_risk_level, fraud_flags FROM affiliate_traffic WHERE ip_address = ? AND fraud_score IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $cacheStmt->execute([$ip]);
        $cached = $cacheStmt->fetch();
    }

    if ($cached) {
        // Update this record too and return cached result — also copy ipqs_raw if available
        $rawStmt2 = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $rawStmt2->execute([$ip]);
        $rawRow2 = $rawStmt2->fetch();
        $cachedRaw = $rawRow2 ? $rawRow2['ipqs_raw'] : null;
        $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ?, ipqs_raw = COALESCE(ipqs_raw, ?) WHERE id = ? AND fraud_score IS NULL");
        $upd->execute([$cached['fraud_score'], $cached['fraud_risk_level'], $cached['fraud_flags'], $cachedRaw, $trafficId]);
        // Also fill country from IPQS if missing
        if ($cachedRaw) {
            $ipqsData = json_decode($cachedRaw, true);
            if (!empty($ipqsData['country_code'])) {
                $pdo->prepare("UPDATE affiliate_traffic SET country = COALESCE(NULLIF(country,''), ?), country_code = COALESCE(NULLIF(country_code,''), ?) WHERE id = ?")
                    ->execute([$ipqsData['region'] ?? $ipqsData['country_code'], $ipqsData['country_code'], $trafficId]);
            }
        }
        // Try to get raw IPQS data from cache
        $rawStmt = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $rawStmt->execute([$ip]);
        $rawRow = $rawStmt->fetch();
        echo json_encode([
            'success' => true,
            'fraudScore' => (int)$cached['fraud_score'],
            'fraudRiskLevel' => $cached['fraud_risk_level'],
            'fraudFlags' => $cached['fraud_flags'],
            'ipqsRaw' => $rawRow ? json_decode($rawRow['ipqs_raw'], true) : null,
            'source' => 'cache'
        ]);
        return;
    }

    // Call IPQS API (manual check bypasses daily limit)
    require_once __DIR__ . '/ipqs.php';
    $ipqs = new IPQS(IPQS_API_KEYS);
    $result = $ipqs->analyzeIP($ip);

    if (!$result) {
        echo json_encode(['error' => 'IPQS API call failed']);
        return;
    }

    $storageData = $ipqs->getStorageData($result);
    incrementIPQSCounter($pdo);

    // Store full raw IPQS response + standard fields
    $rawJson = json_encode($result);
    $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ?, ipqs_raw = ? WHERE id = ?");
    $upd->execute([$storageData['fraud_score'], $storageData['fraud_risk_level'], $storageData['fraud_flags'], $rawJson, $trafficId]);

    // Also fill country from IPQS if missing on the record
    if (!empty($result['country_code'])) {
        $countryStmt = $pdo->prepare("UPDATE affiliate_traffic SET country = COALESCE(NULLIF(country,''), ?), country_code = COALESCE(NULLIF(country_code,''), ?) WHERE id = ?");
        $countryStmt->execute([$result['region'] ?? $result['country_code'], $result['country_code'], $trafficId]);
    }

    echo json_encode([
        'success' => true,
        'fraudScore' => $storageData['fraud_score'],
        'fraudRiskLevel' => $storageData['fraud_risk_level'],
        'fraudFlags' => $storageData['fraud_flags'],
        'ipqsRaw' => $result,
        'country' => $result['country_code'] ?? null,
        'source' => 'api'
    ]);
}

function checkIPFraudDirect($pdo) {
    $data = getPostData();
    $ip = $data['ip_address'] ?? null;

    if (empty($ip)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ip_address']);
        return;
    }

    // Check cache first (any traffic record with this IP already scored)
    $cacheStmt = $pdo->prepare("SELECT fraud_score, fraud_risk_level, fraud_flags FROM affiliate_traffic WHERE ip_address = ? AND fraud_score IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
    $cacheStmt->execute([$ip]);
    $cached = $cacheStmt->fetch();

    if ($cached) {
        // Try to get raw IPQS data from cache
        $rawStmt = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $rawStmt->execute([$ip]);
        $rawRow = $rawStmt->fetch();

        if ($rawRow) {
            echo json_encode([
                'success' => true,
                'fraudScore' => (int)$cached['fraud_score'],
                'fraudRiskLevel' => $cached['fraud_risk_level'],
                'fraudFlags' => $cached['fraud_flags'],
                'ipqsRaw' => json_decode($rawRow['ipqs_raw'], true),
                'source' => 'cache'
            ]);
            return;
        }
        // ipqs_raw missing — fall through to call IPQS API and store the raw data
    }

    // Call IPQS API
    require_once __DIR__ . '/ipqs.php';
    $ipqs = new IPQS(IPQS_API_KEYS);
    $result = $ipqs->analyzeIP($ip);

    if (!$result) {
        // Return cached score if we have it, even without raw data
        if ($cached) {
            echo json_encode([
                'success' => true,
                'fraudScore' => (int)$cached['fraud_score'],
                'fraudRiskLevel' => $cached['fraud_risk_level'],
                'fraudFlags' => $cached['fraud_flags'],
                'ipqsRaw' => null,
                'source' => 'cache_no_raw'
            ]);
            return;
        }
        echo json_encode(['error' => 'IPQS API call failed']);
        return;
    }

    $storageData = $ipqs->getStorageData($result);
    incrementIPQSCounter($pdo);

    // Store ipqs_raw on all traffic records with this IP that don't have it
    $rawJson = json_encode($result);
    $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ?, ipqs_raw = COALESCE(ipqs_raw, ?) WHERE ip_address = ?");
    $upd->execute([$storageData['fraud_score'], $storageData['fraud_risk_level'], $storageData['fraud_flags'], $rawJson, $ip]);

    echo json_encode([
        'success' => true,
        'fraudScore' => $storageData['fraud_score'],
        'fraudRiskLevel' => $storageData['fraud_risk_level'],
        'fraudFlags' => $storageData['fraud_flags'],
        'ipqsRaw' => $result,
        'source' => 'api'
    ]);
}

function uploadAffiliates($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $csvData = $data['csv_data'] ?? '';

    if (empty($domainId) || empty($csvData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing domain_id or csv_data']);
        return;
    }

    $lines = preg_split('/\r?\n/', trim($csvData));
    $inserted = 0;

    // Clear existing names for this domain
    $pdo->prepare("DELETE FROM affiliate_names WHERE domain_id = ?")->execute([$domainId]);

    $stmt = $pdo->prepare("INSERT INTO affiliate_names (domain_id, aff_id, name) VALUES (?, ?, ?)");

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        // Support CSV: "id,name" or "id\tname"
        $parts = preg_split('/[,\t]/', $line, 2);
        if (count($parts) < 2) continue;
        $affId = trim($parts[0], ' "\'');
        $name = trim($parts[1], ' "\'');
        if (empty($affId) || empty($name)) continue;
        // Skip header row
        if (strtolower($affId) === 'id' || strtolower($affId) === 'aff_id' || strtolower($affId) === 'affiliate_id') continue;
        $stmt->execute([$domainId, $affId, $name]);
        $inserted++;
    }

    echo json_encode(['success' => true, 'count' => $inserted]);
}

function getAffiliateNames($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';

    if (empty($domainId)) {
        echo json_encode(['success' => true, 'names' => []]);
        return;
    }

    $stmt = $pdo->prepare("SELECT aff_id, name FROM affiliate_names WHERE domain_id = ?");
    $stmt->execute([$domainId]);
    $rows = $stmt->fetchAll();

    $names = [];
    foreach ($rows as $r) {
        $names[$r['aff_id']] = $r['name'];
    }

    echo json_encode(['success' => true, 'names' => $names]);
}

/**
 * ClickBank API proxy — fetches data from ClickBank REST API
 * Supported types: orders, tickets, quickstats, subscriptions, products, shipping, refunds, chargebacks
 */
function getCBApiData($pdo) {
    $data = getPostData();
    $type = $data['type'] ?? 'orders';
    $startDate = $data['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $endDate = $data['end_date'] ?? date('Y-m-d');
    $vendor = $data['vendor'] ?? '';

    if (!defined('CB_API_KEY') || empty(CB_API_KEY)) {
        echo json_encode(['success' => false, 'error' => 'ClickBank API key not configured']);
        return;
    }

    $baseUrl = 'https://api.clickbank.com/rest/1.3/';
    $account = defined('CB_ACCOUNT') ? CB_ACCOUNT : $vendor;

    // ── TICKETS: single call, 7-day chunk limit, paginated ───────────────────
    // Per CB API docs: /tickets/list has NO vendor/account filter — returns all
    // tickets for the API key. Date range is capped at 7 days max per request,
    // so we split longer ranges into 7-day windows and merge the pages.
    if ($type === 'tickets') {
        $allRows = [];
        $seenIds = [];
        $errors  = [];
        $cursor  = new DateTime($startDate);
        $endDt   = new DateTime($endDate);
        while ($cursor <= $endDt) {
            $chunkEnd = clone $cursor;
            $chunkEnd->modify('+6 days');
            if ($chunkEnd > $endDt) $chunkEnd = clone $endDt;
            $u = $baseUrl . 'tickets/list?startDate=' . urlencode($cursor->format('Y-m-d'))
               . '&endDate=' . urlencode($chunkEnd->format('Y-m-d'));
            $page = 1;
            while ($page <= 50) {
                $ch2 = curl_init($u);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Authorization: ' . CB_API_KEY, 'Page: ' . $page],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $resp2 = curl_exec($ch2);
                $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $err2  = curl_error($ch2);
                curl_close($ch2);
                if ($err2 || ($code2 !== 200 && $code2 !== 206)) {
                    if ($page === 1) $errors[] = $err2 ?: ('HTTP ' . $code2 . ' [' . $cursor->format('Y-m-d') . ']');
                    break;
                }
                $resp2 = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$resp2));
                if ($resp2 === '') break;
                $dec = json_decode($resp2, true);
                if ($dec === null && json_last_error() !== JSON_ERROR_NONE) {
                    $xml2 = @simplexml_load_string(substr($resp2, strpos($resp2, '<') ?: 0));
                    if ($xml2) $dec = json_decode(json_encode($xml2), true);
                }
                if (!is_array($dec)) break;
                $rows = [];
                if (isset($dec['ticketData'])) { $d = $dec['ticketData']; $rows = (is_array($d) && isset($d[0])) ? $d : [$d]; }
                elseif (isset($dec['ticketList']['ticketData'])) { $d = $dec['ticketList']['ticketData']; $rows = (is_array($d) && isset($d[0])) ? $d : [$d]; }
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $tid = $row['ticketId'] ?? ($row['id'] ?? null);
                    if ($tid && isset($seenIds[$tid])) continue;
                    if ($tid) $seenIds[$tid] = true;
                    $allRows[] = $row;
                }
                if ($code2 === 200 || count($rows) === 0) break;
                $page++;
            }
            $cursor->modify('+7 days');
        }
        echo json_encode([
            'success' => true,
            'data'    => ['ticketData' => $allRows],
            'type'    => 'tickets',
            'errors'  => $errors,
            'total'   => count($allRows),
        ]);
        return;
    }

    // ── ANALYTICS: computed from orders2/list (quickstats API returns empty) ───
    // Fetch all transaction types (SALE, RFND, CGBK, REBILL, UPSELL) per vendor
    // using the same orders2/list endpoint, then group by date+vendor client-side.
    if ($type === 'analytics') {
        $nicks = getCbNicknames($pdo);
        if (empty($nicks)) {
            echo json_encode(['success' => false, 'error' => 'No ClickBank nicknames configured.']);
            return;
        }

        $allOrders   = [];
        $perNickname = [];
        $errors      = [];

        foreach ($nicks as $nick) {
            $perNickname[$nick] = 0;
            $page = 1;
            $pageErrors = [];

            while ($page <= 50) {
                $u = $baseUrl . 'orders2/list?vendor=' . urlencode($nick)
                   . '&startDate=' . urlencode($startDate)
                   . '&endDate='   . urlencode($endDate)
                   . '&count=100';
                $ch = curl_init($u);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_HTTPHEADER     => [
                        'Accept: application/json',
                        'Authorization: ' . CB_API_KEY,
                        'Page: ' . $page,
                    ],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err  = curl_error($ch);
                curl_close($ch);

                if ($err || ($code !== 200 && $code !== 206)) {
                    $pageErrors[] = 'page ' . $page . ': ' . ($err ?: 'HTTP ' . $code);
                    break;
                }

                $raw = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$body));
                $dec = json_decode($raw, true);
                $orders = [];
                if (isset($dec['orderData'])) {
                    $o = $dec['orderData'];
                    $orders = (is_array($o) && isset($o[0])) ? $o : [$o];
                }
                foreach ($orders as $order) {
                    if (!is_array($order)) continue;
                    $order['_nickname'] = $nick;
                    $allOrders[]         = $order;
                    $perNickname[$nick]++;
                }

                if ($code === 200) break; // 200 = last page
                $page++;
            }

            if (!empty($pageErrors)) $errors[$nick] = implode('; ', $pageErrors);
        }

        // Group by date + vendor, aggregate by transaction type
        $byKey = [];
        foreach ($allOrders as $order) {
            $nick    = $order['_nickname'] ?? '—';
            $dateRaw = $order['transactionTime'] ?? $order['orderDate'] ?? '';
            $date    = substr((string)$dateRaw, 0, 10); // YYYY-MM-DD
            if (!$date || $date === '0000') continue;

            $key = $date . '|' . $nick;
            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'date'        => $date,
                    'vendor'      => $nick,
                    '_nickname'   => $nick,
                    'gross'       => 0.0,
                    'refunds'     => 0.0,
                    'chargebacks' => 0.0,
                    'net'         => 0.0,
                    'count'       => 0,
                    'refCount'    => 0,
                    'cbCount'     => 0,
                    'currency'    => $order['currency'] ?? 'USD',
                ];
            }

            $amount = (float)($order['totalOrderAmount'] ?? $order['amount'] ?? 0);
            $txType = strtoupper(trim((string)($order['transactionType'] ?? 'SALE')));

            if (in_array($txType, ['SALE', 'REBILL', 'UPSELL'], true)) {
                $byKey[$key]['gross'] += $amount;
                $byKey[$key]['count']++;
            } elseif (in_array($txType, ['RFND', 'REFUND'], true)) {
                $byKey[$key]['refunds'] += abs($amount);
                $byKey[$key]['refCount']++;
            } elseif (in_array($txType, ['CGBK', 'CHARGEBACK'], true)) {
                $byKey[$key]['chargebacks'] += abs($amount);
                $byKey[$key]['cbCount']++;
            }
        }

        $allRows = [];
        foreach ($byKey as &$row) {
            $row['net']          = round($row['gross'] - $row['refunds'] - $row['chargebacks'], 2);
            $row['gross']        = round($row['gross'],        2);
            $row['refunds']      = round($row['refunds'],      2);
            $row['chargebacks']  = round($row['chargebacks'],  2);
            $allRows[] = $row;
        }
        unset($row);

        // Sort by date descending, then vendor name
        usort($allRows, function($a, $b) {
            $cmp = strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? ''));
            return $cmp !== 0 ? $cmp : strcmp((string)($a['_nickname'] ?? ''), (string)($b['_nickname'] ?? ''));
        });

        echo json_encode([
            'success'     => true,
            'data'        => ['analyticsData' => $allRows],
            'type'        => 'analytics',
            'nicknames'   => $nicks,
            'perNickname' => $perNickname,
            'errors'      => $errors,
            'total'       => count($allRows),
        ]);
        return;
    }

    // ── SHIPPING: single call, paginated, no vendor filter ──────────────────
    // Per CB API docs: /shipping2/list returns physical goods orders.
    // No vendor/account filter. Paginate via Page: N header (200=done, 206=more).
    if ($type === 'shipping') {
        $status = trim((string)($data['status'] ?? 'all'));
        $u = $baseUrl . 'shipping2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
        if ($status !== '' && $status !== 'all') $u .= '&status=' . urlencode($status);
        $allRows      = [];
        $seenReceipts = [];
        $errors       = [];
        $page = 1;
        while ($page <= 50) {
            $ch2 = curl_init($u);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Authorization: ' . CB_API_KEY, 'Page: ' . $page],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $resp2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            $err2  = curl_error($ch2);
            curl_close($ch2);
            if ($err2 || ($code2 !== 200 && $code2 !== 206)) {
                if ($page === 1) $errors[] = $err2 ?: ('HTTP ' . $code2 . ': ' . substr((string)$resp2, 0, 100));
                break;
            }
            $resp2 = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$resp2));
            if ($resp2 === '') break;
            $dec = json_decode($resp2, true);
            if ($dec === null && json_last_error() !== JSON_ERROR_NONE) {
                $xml2 = @simplexml_load_string(substr($resp2, strpos($resp2, '<') ?: 0));
                if ($xml2) $dec = json_decode(json_encode($xml2), true);
            }
            if (!is_array($dec)) break;
            // CB may wrap rows under orderShipData, shipData, or orderData
            $rows = [];
            foreach (['orderShipData', 'shipData', 'orderData'] as $rk) {
                if (isset($dec[$rk])) {
                    $d = $dec[$rk];
                    $rows = (is_array($d) && isset($d[0])) ? $d : [$d];
                    break;
                }
            }
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $receipt = $row['receipt'] ?? null;
                if ($receipt && isset($seenReceipts[$receipt])) continue;
                if ($receipt) $seenReceipts[$receipt] = true;
                $allRows[] = $row;
            }
            if ($code2 === 200 || count($rows) === 0) break;
            $page++;
        }
        echo json_encode([
            'success' => true,
            'data'    => ['orderShipData' => $allRows],
            'type'    => 'shipping',
            'errors'  => $errors,
            'total'   => count($allRows),
        ]);
        return;
    }

    // ── Orders / Refunds / Chargebacks: per-vendor fetch + pagination ─────────
    // Per CB API docs (/orders2/list): filter by `vendor=NICK`. Max 100/page;
    // HTTP 206 = more pages, HTTP 200 = last page. Page via `Page: N` header.
    if (in_array($type, ['orders', 'refunds', 'chargebacks'], true)) {
        $nicks = getCbNicknames($pdo);
        if (empty($nicks)) {
            echo json_encode(['success' => false, 'error' => 'No ClickBank nicknames configured.']);
            return;
        }
        $allRows      = [];
        $seenReceipts = [];
        $perNickname  = [];
        $errors       = [];
        foreach ($nicks as $nick) {
            if ($type === 'refunds') {
                $u = $baseUrl . 'orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate) . '&type=RFND&vendor=' . urlencode($nick);
            } elseif ($type === 'chargebacks') {
                $u = $baseUrl . 'orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate) . '&type=CGBK&vendor=' . urlencode($nick);
            } else {
                $u = $baseUrl . 'orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate) . '&vendor=' . urlencode($nick);
            }
            $page     = 1;
            $nickRows = [];
            $nickErr  = null;
            while ($page <= 50) {
                $ch2 = curl_init($u);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 30,
                    CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Authorization: ' . CB_API_KEY, 'Page: ' . $page],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $resp2 = curl_exec($ch2);
                $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $err2  = curl_error($ch2);
                curl_close($ch2);
                if ($err2 || ($code2 !== 200 && $code2 !== 206)) {
                    if ($page === 1) $nickErr = $err2 ?: ('HTTP ' . $code2 . ': ' . substr((string)$resp2, 0, 100));
                    break;
                }
                $resp2 = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$resp2));
                if ($resp2 === '') break;
                $dec = json_decode($resp2, true);
                if ($dec === null && json_last_error() !== JSON_ERROR_NONE) {
                    $xml2 = @simplexml_load_string(substr($resp2, strpos($resp2, '<') ?: 0));
                    if ($xml2) $dec = json_decode(json_encode($xml2), true);
                }
                if (!is_array($dec)) break;
                $rows = [];
                if (isset($dec['orderData'])) { $d = $dec['orderData']; $rows = (is_array($d) && isset($d[0])) ? $d : [$d]; }
                elseif (isset($dec['orderList']['orderData'])) { $d = $dec['orderList']['orderData']; $rows = (is_array($d) && isset($d[0])) ? $d : [$d]; }
                $nickRows = array_merge($nickRows, $rows);
                if ($code2 === 200 || count($rows) === 0) break;
                $page++;
            }
            if ($nickErr) $errors[$nick] = $nickErr;
            $added = 0;
            foreach ($nickRows as $row) {
                if (!is_array($row)) continue;
                $row['_nickname'] = $nick;
                $receipt = $row['receipt'] ?? ($row['receiptId'] ?? null);
                if ($receipt && isset($seenReceipts[$receipt])) continue;
                if ($receipt) $seenReceipts[$receipt] = true;
                $allRows[] = $row;
                $added++;
            }
            $perNickname[$nick] = $added;
        }
        echo json_encode([
            'success'     => true,
            'data'        => ['orderData' => $allRows],
            'type'        => $type,
            'nicknames'   => $nicks,
            'perNickname' => $perNickname,
            'errors'      => $errors,
            'total'       => count($allRows),
        ]);
        return;
    }
    // ─────────────────────────────────────────────────────────────────────────

    // Build URL based on type — per official CB API docs:
    // Analytics: /analytics/VENDOR/AFFILIATE (per-affiliate stats with hops, sales, etc.)
    // Orders: /orders2/list (v2 endpoint)
    // Tickets: /tickets/list
    // Products: /products/list
    // Shipping: /shipping2/list
    // Quickstats: /quickstats/count
    switch ($type) {
        case 'analytics':
            // Use quickstats/list for per-affiliate breakdown (analytics/VENDOR/AFFILIATE times out on CB side)
            $url = $baseUrl . 'quickstats/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
            if ($account) $url .= '&account=' . urlencode($account);
            break;
        case 'quickstats':
            $url = $baseUrl . 'quickstats/count?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
            if ($account) $url .= '&account=' . urlencode($account);
            break;
        case 'tickets':
            $url = $baseUrl . 'tickets/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
            break;
        case 'subscriptions':
            // Subscription details via Analytics API
            $url = $baseUrl . 'analytics/VENDOR/subscription/details?account=' . urlencode($account);
            break;
        case 'products':
            // Fetch products for EVERY configured nickname (cb_vendor on domains +
            // CB_ACCOUNT fallback), then merge. Each product is tagged with _nickname
            // so the UI can group/filter/drill-in per site.
            $nicks = getCbNicknames($pdo);
            if (empty($nicks)) {
                echo json_encode(['success' => false, 'error' => 'No ClickBank nicknames configured. Add cb_vendor to a domain or set CB_ACCOUNT.']);
                return;
            }
            $typeFilter = $data['product_type'] ?? '';
            // Optional single-nickname narrowing from UI filter
            $onlyNick = trim((string)($data['nickname'] ?? ''));
            if ($onlyNick !== '') {
                $onlyNick = strtolower($onlyNick);
                $nicks = array_values(array_filter($nicks, function($n) use ($onlyNick) { return strtolower($n) === $onlyNick; }));
                if (empty($nicks)) {
                    echo json_encode(['success' => true, 'data' => ['productList' => ['product' => []]], 'type' => 'products', 'nicknames' => [], 'perNickname' => [], 'errors' => [], 'total' => 0]);
                    return;
                }
            }
            $allProducts = [];
            $perNickname = [];
            $errors = [];
            $debugRaws = []; // raw CB response per nick (truncated) for browser debugging
            foreach ($nicks as $nick) {
                $u = $baseUrl . 'products/list?site=' . urlencode($nick);
                if ($typeFilter === 'STANDARD' || $typeFilter === 'RECURRING') {
                    $u .= '&type=' . urlencode($typeFilter);
                }
                $ch2 = curl_init($u);
                curl_setopt_array($ch2, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: ' . CB_API_KEY],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $resp2 = curl_exec($ch2);
                $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $err2  = curl_error($ch2);
                curl_close($ch2);

                if ($err2 || $code2 !== 200) {
                    $errors[$nick] = $err2 ?: ('HTTP ' . $code2 . ' body:' . substr((string)$resp2, 0, 200));
                    $perNickname[$nick] = 0;
                    $debugRaws[$nick] = 'HTTP ' . $code2 . ' | ' . substr((string)$resp2, 0, 500);
                    continue;
                }
                $resp2 = preg_replace('/^\xEF\xBB\xBF/', '', (string)$resp2);
                $resp2 = trim($resp2);
                $debugRaws[$nick] = substr($resp2, 0, 600); // always capture raw for diagnostics
                if ($resp2 === '') { $perNickname[$nick] = 0; continue; }

                $dec = json_decode($resp2, true);
                if ($dec === null && json_last_error() !== JSON_ERROR_NONE) {
                    $xmlStart = strpos($resp2, '<');
                    $xmlContent = $xmlStart !== false ? substr($resp2, $xmlStart) : $resp2;
                    $xml2 = @simplexml_load_string($xmlContent);
                    if ($xml2) $dec = json_decode(json_encode($xml2), true);
                }
                // CB /products/list returns {"products":{"product":[...]},"total_record_count":N}
                // The root key is "products" (NOT "productList"). Extract the product array directly.
                $products = [];
                if (isset($dec['products']['product'])) {
                    // Real CB format (confirmed via debug endpoint 2026-04-09)
                    $pl = $dec['products']['product'];
                    $products = (isset($pl[0]) && is_array($pl[0])) ? $pl : [$pl];
                } elseif (isset($dec['productList']['product'])) {
                    $pl = $dec['productList']['product'];
                    $products = (isset($pl[0]) && is_array($pl[0])) ? $pl : [$pl];
                } elseif (isset($dec['product'])) {
                    $pl = $dec['product'];
                    $products = (isset($pl[0]) && is_array($pl[0])) ? $pl : [$pl];
                } elseif (is_array($dec) && isset($dec[0])) {
                    $products = $dec;
                }
                // CB's JSON API wraps each list item in a single-key envelope.
                // Observed shapes: {"product":{...}} and {"0":{...}} (numeric index).
                // Unwrap any item that is a single-key array whose only value is an array,
                // and whose key is either "product" or a numeric/numeric-string index.
                // Unwrap CB's single-key envelope BEFORE tagging with _nickname.
                // Exclude '_nickname' from the key count in case it was already set
                // by a previous iteration or a stale code path.
                $products = array_map(function($item) {
                    if (!is_array($item)) return $item;
                    $realKeys = array_values(array_filter(array_keys($item), function($k) { return $k !== '_nickname'; }));
                    if (count($realKeys) !== 1) return $item;
                    $k = $realKeys[0];
                    if (($k === 'product' || is_numeric($k)) && is_array($item[$k])) {
                        $inner = $item[$k];
                        if (isset($item['_nickname'])) $inner['_nickname'] = $item['_nickname'];
                        return $inner;
                    }
                    return $item;
                }, $products);
                // Tag each product with its source nickname AFTER unwrapping.
                foreach ($products as $k => $p) {
                    if (is_array($p)) { $products[$k]['_nickname'] = $nick; }
                }
                $perNickname[$nick] = count($products);
                $allProducts = array_merge($allProducts, $products);
            }
            echo json_encode([
                'success'     => true,
                'data'        => ['productList' => ['product' => $allProducts]],
                'type'        => 'products',
                'nicknames'   => $nicks,
                'perNickname' => $perNickname,
                'errors'      => $errors,
                'debug'       => $debugRaws,
                'total'       => count($allProducts),
            ]);
            return;
        case 'shipping':
            $url = $baseUrl . 'shipping2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
            break;
        case 'refunds':
            $url = $baseUrl . 'orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate) . '&type=RFND';
            break;
        case 'chargebacks':
            $url = $baseUrl . 'orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate) . '&type=CGBK';
            break;
        case 'orders_count':
            $url = $baseUrl . 'orders2/count?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
            break;
        default: // orders
            $url = $baseUrl . 'orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
            break;
    }

    // Analytics/subscription endpoints can be slow for large date ranges
    $timeout = ($type === 'subscriptions') ? 60 : 30;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: ' . CB_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'cURL error: ' . $curlError]);
        return;
    }

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'ClickBank API returned HTTP ' . $httpCode, 'response' => $response, 'url' => $url, 'type' => $type]);
        return;
    }

    // Empty response = no data (ClickBank returns empty body for no results)
    if (empty(trim($response))) {
        echo json_encode(['success' => true, 'data' => [], 'type' => $type, 'httpCode' => $httpCode]);
        return;
    }

    // Strip BOM and whitespace
    $response = preg_replace('/^\xEF\xBB\xBF/', '', $response);
    $response = trim($response);

    // Try JSON first
    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        // Try XML — strip any leading non-XML chars
        $xmlStart = strpos($response, '<');
        $xmlContent = $xmlStart !== false ? substr($response, $xmlStart) : $response;
        $xml = @simplexml_load_string($xmlContent);
        if ($xml) {
            $decoded = json_decode(json_encode($xml), true);
        } else {
            // Try line-based key:value format (some CB endpoints return this)
            $lines = explode("\n", $response);
            $kvData = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, ':') !== false) {
                    list($k, $v) = explode(':', $line, 2);
                    $kvData[trim($k)] = trim($v);
                } elseif (!empty($line)) {
                    $kvData[] = $line;
                }
            }
            if (!empty($kvData)) {
                $decoded = $kvData;
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to parse ClickBank response', 'raw' => substr($response, 0, 500), 'type' => $type, 'url' => $url]);
                return;
            }
        }
    }

    echo json_encode(['success' => true, 'data' => $decoded, 'type' => $type, 'httpCode' => $httpCode]);
}

// ================================================================
// CLICKBANK NICKNAMES (aka "sites")
// Pulls every distinct cb_vendor from the domains table so the Products API
// can be queried across ALL configured sites in one go. Falls back to the
// hardcoded CB_ACCOUNT if the table is empty or the column is missing.
// ================================================================
function getCbNicknames($pdo) {
    $nicks = [];
    try {
        $stmt = $pdo->query("SELECT DISTINCT cb_vendor FROM domains WHERE cb_vendor IS NOT NULL AND cb_vendor != '' AND status != 'deleted' ORDER BY cb_vendor");
        if ($stmt) {
            foreach ($stmt->fetchAll() as $row) {
                $v = strtolower(trim((string)$row['cb_vendor']));
                if ($v !== '' && !in_array($v, $nicks, true)) $nicks[] = $v;
            }
        }
    } catch (Exception $e) { /* table or column missing — fall through */ }

    // Always include the hardcoded CB_ACCOUNT as a final fallback so a fresh
    // install with no domains configured still returns something useful.
    if (defined('CB_ACCOUNT')) {
        $fallback = strtolower(trim(CB_ACCOUNT));
        if ($fallback !== '' && !in_array($fallback, $nicks, true)) $nicks[] = $fallback;
    }
    return $nicks;
}

function getCbNicknamesEndpoint($pdo) {
    $nicks = getCbNicknames($pdo);
    echo json_encode(['success' => true, 'nicknames' => $nicks]);
}

// ================================================================
// CLICKBANK API KEY MANAGEMENT
// Writes/reads the runtime .cb_key file loaded by config.php on next request.
// Never deletes existing orders, settings, or any CB-cached data — only the
// auth header value changes. Current constant stays live for THIS request.
// ================================================================
function updateCbApiKey($pdo) {
    $data = getPostData();
    $newKey = trim((string)($data['api_key'] ?? ''));

    if ($newKey === '') {
        echo json_encode(['success' => false, 'error' => 'API key cannot be empty']);
        return;
    }
    // CB keys look like: API-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX (uppercase alnum)
    if (!preg_match('/^API-[A-Z0-9]{16,}$/', $newKey)) {
        echo json_encode(['success' => false, 'error' => 'Invalid format. Expected: API-XXXX... (uppercase letters & digits)']);
        return;
    }

    $file = __DIR__ . '/.cb_key';
    // Write atomically via temp file + rename so a concurrent read never sees a partial write
    $tmp = $file . '.tmp';
    $bytes = @file_put_contents($tmp, $newKey, LOCK_EX);
    if ($bytes === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write key file — check server write permissions']);
        return;
    }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        echo json_encode(['success' => false, 'error' => 'Failed to commit key file']);
        return;
    }

    // Return masked preview so the UI can render confirmation without leaking
    $masked = substr($newKey, 0, 8) . str_repeat('*', max(0, strlen($newKey) - 12)) . substr($newKey, -4);
    echo json_encode([
        'success' => true,
        'masked'  => $masked,
        'message' => 'ClickBank API key updated. Existing data is preserved — new key will be used on next fetch.'
    ]);
}

function getCbApiKeyMasked($pdo) {
    $key = defined('CB_API_KEY') ? CB_API_KEY : '';
    if ($key === '') {
        echo json_encode(['success' => true, 'masked' => '', 'configured' => false]);
        return;
    }
    $masked = strlen($key) > 12
        ? substr($key, 0, 8) . str_repeat('*', strlen($key) - 12) . substr($key, -4)
        : str_repeat('*', strlen($key));
    echo json_encode(['success' => true, 'masked' => $masked, 'configured' => true]);
}

// ================================================================
// CLICKBANK PRODUCT DETAIL (GET /products/{sku})
// ================================================================
function getCbProductDetail($pdo) {
    $data = getPostData();
    $sku  = trim((string)($data['sku'] ?? ''));
    $site = trim((string)($data['site'] ?? (defined('CB_ACCOUNT') ? CB_ACCOUNT : '')));

    if ($sku === '') {
        echo json_encode(['success' => false, 'error' => 'SKU is required']);
        return;
    }
    if ($site === '') {
        echo json_encode(['success' => false, 'error' => 'Site (vendor nickname) is required']);
        return;
    }
    if (!defined('CB_API_KEY') || CB_API_KEY === '') {
        echo json_encode(['success' => false, 'error' => 'ClickBank API key not configured']);
        return;
    }

    $url = 'https://api.clickbank.com/rest/1.3/products/' . urlencode($sku) . '?site=' . urlencode($site);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: ' . CB_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) { echo json_encode(['success' => false, 'error' => 'cURL: ' . $err]); return; }
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'CB HTTP ' . $httpCode, 'response' => substr((string)$response, 0, 500)]);
        return;
    }

    $response = preg_replace('/^\xEF\xBB\xBF/', '', (string)$response);
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        $xmlStart = strpos($response, '<');
        $xml = $xmlStart !== false ? @simplexml_load_string(substr($response, $xmlStart)) : null;
        if ($xml) $decoded = json_decode(json_encode($xml), true);
    }
    echo json_encode(['success' => true, 'data' => $decoded]);
}

// ================================================================
// CLICKBANK PRODUCT CREATE (PUT /products/{sku})
// ================================================================
function createCbProduct($pdo) {
    $data = getPostData();
    $sku  = trim((string)($data['sku'] ?? ''));
    $site = trim((string)($data['site'] ?? (defined('CB_ACCOUNT') ? CB_ACCOUNT : '')));

    if ($sku === '') { echo json_encode(['success' => false, 'error' => 'SKU is required']); return; }
    if (!preg_match('/^[A-Z0-9_]{1,20}$/i', $sku)) {
        echo json_encode(['success' => false, 'error' => 'SKU must be 1-20 alphanumeric characters or underscores']);
        return;
    }
    if (!defined('CB_API_KEY') || CB_API_KEY === '') {
        echo json_encode(['success' => false, 'error' => 'ClickBank API key not configured']);
        return;
    }

    // Required per CB docs: title, price, currency, language, site, pitchPage, thankYouPage,
    // and at least one component flag (digital/physical/digitalRecurring/physicalRecurring)
    $required = ['title', 'price', 'currency', 'language', 'pitchPage', 'thankYouPage'];
    foreach ($required as $k) {
        if (!isset($data[$k]) || trim((string)$data[$k]) === '') {
            echo json_encode(['success' => false, 'error' => "Missing required field: $k"]);
            return;
        }
    }

    // Build form body (CB PUT expects form-encoded body per docs)
    $body = [
        'site' => $site,
        'title' => $data['title'],
        'price' => $data['price'],
        'currency' => strtoupper((string)$data['currency']),
        'language' => strtoupper((string)$data['language']),
        'pitchPage' => $data['pitchPage'],
        'thankYouPage' => $data['thankYouPage'],
    ];
    // Component flags — default to digital:true if nothing selected
    $digital = !empty($data['digital']);
    $physical = !empty($data['physical']);
    $digitalRec = !empty($data['digitalRecurring']);
    $physicalRec = !empty($data['physicalRecurring']);
    if (!$digital && !$physical && !$digitalRec && !$physicalRec) { $digital = true; }
    $body['digital'] = $digital ? 'true' : 'false';
    $body['physical'] = $physical ? 'true' : 'false';
    $body['digitalRecurring'] = $digitalRec ? 'true' : 'false';
    $body['physicalRecurring'] = $physicalRec ? 'true' : 'false';

    if (!empty($data['description'])) $body['description'] = $data['description'];
    if (!empty($data['categories']))   $body['categories']   = strtoupper((string)$data['categories']);
    if (isset($data['purchaseCommission'])) $body['purchaseCommission'] = $data['purchaseCommission'];

    // Recurring extras
    if ($digitalRec || $physicalRec) {
        if (!empty($data['frequency']))   $body['frequency']   = strtoupper((string)$data['frequency']);
        if (isset($data['duration']))     $body['duration']    = $data['duration'];
        if (isset($data['rebillPrice']))  $body['rebillPrice'] = $data['rebillPrice'];
        if (isset($data['rebillCommission'])) $body['rebillCommission'] = $data['rebillCommission'];
        if (isset($data['trialPeriod']))  $body['trialPeriod'] = $data['trialPeriod'];
    }

    $url = 'https://api.clickbank.com/rest/1.3/products/' . urlencode($sku);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => http_build_query($body),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: ' . CB_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) { echo json_encode(['success' => false, 'error' => 'cURL: ' . $err]); return; }
    if ($httpCode < 200 || $httpCode >= 300) {
        echo json_encode(['success' => false, 'error' => 'CB HTTP ' . $httpCode, 'response' => substr((string)$response, 0, 500)]);
        return;
    }
    echo json_encode(['success' => true, 'sku' => $sku, 'response' => $response]);
}

// ================================================================
// CLICKBANK PRODUCT DELETE (DELETE /products/{sku})
// ================================================================
function deleteCbProduct($pdo) {
    $data = getPostData();
    $sku  = trim((string)($data['sku'] ?? ''));
    $site = trim((string)($data['site'] ?? (defined('CB_ACCOUNT') ? CB_ACCOUNT : '')));

    if ($sku === '') { echo json_encode(['success' => false, 'error' => 'SKU is required']); return; }
    if ($site === '') { echo json_encode(['success' => false, 'error' => 'Site is required']); return; }
    if (!defined('CB_API_KEY') || CB_API_KEY === '') {
        echo json_encode(['success' => false, 'error' => 'ClickBank API key not configured']);
        return;
    }

    $url = 'https://api.clickbank.com/rest/1.3/products/' . urlencode($sku) . '?site=' . urlencode($site);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: ' . CB_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) { echo json_encode(['success' => false, 'error' => 'cURL: ' . $err]); return; }
    if ($httpCode < 200 || $httpCode >= 300) {
        echo json_encode(['success' => false, 'error' => 'CB HTTP ' . $httpCode, 'response' => substr((string)$response, 0, 500)]);
        return;
    }
    echo json_encode(['success' => true, 'sku' => $sku]);
}

// ================================================================
// CLICKBANK ORDER SYNC — fetches orders from CB API into local orders table
// ================================================================

function deleteTestOrders($pdo) {
    $data = getPostData();
    $domainId = intval($data['domain_id'] ?? 0);
    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id']);
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM orders WHERE domain_id = ? AND (is_test = 1 OR status LIKE '%TEST%')");
    $stmt->execute([$domainId]);
    $deleted = $stmt->rowCount();
    echo json_encode(['success' => true, 'deleted' => $deleted, 'message' => "Deleted $deleted test orders"]);
}

function syncCbOrders($pdo) {
    $data = getPostData();
    $domainId = intval($data['domain_id'] ?? 0);
    $startDate = $data['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $data['end_date'] ?? date('Y-m-d');

    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id']);
        return;
    }

    // Get vendor nickname from domain
    $dStmt = $pdo->prepare("SELECT cb_vendor, platform FROM domains WHERE id = ?");
    $dStmt->execute([$domainId]);
    $domain = $dStmt->fetch();
    if (!$domain || $domain['platform'] !== 'clickbank') {
        echo json_encode(['success' => false, 'error' => 'Not a ClickBank domain']);
        return;
    }

    if (!defined('CB_API_KEY') || empty(CB_API_KEY)) {
        echo json_encode(['success' => false, 'error' => 'ClickBank API key not configured']);
        return;
    }

    $vendor = $domain['cb_vendor'];
    $account = defined('CB_ACCOUNT') ? CB_ACCOUNT : $vendor;

    // Fetch orders from CB API
    $url = 'https://api.clickbank.com/rest/1.3/orders2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: ' . CB_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['success' => false, 'error' => 'cURL error: ' . $curlError]);
        return;
    }

    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'ClickBank API returned HTTP ' . $httpCode]);
        return;
    }

    if (empty(trim($response))) {
        echo json_encode(['success' => true, 'synced' => 0, 'skipped' => 0, 'message' => 'No orders found in date range']);
        return;
    }

    // Parse response
    $response = preg_replace('/^\xEF\xBB\xBF/', '', trim($response));
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        $xmlStart = strpos($response, '<');
        $xmlContent = $xmlStart !== false ? substr($response, $xmlStart) : $response;
        $xml = @simplexml_load_string($xmlContent);
        if ($xml) {
            $decoded = json_decode(json_encode($xml), true);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to parse CB API response']);
            return;
        }
    }

    // Extract orders array
    $orders = [];
    if (isset($decoded['orderData'])) {
        $orders = is_array($decoded['orderData']) ? $decoded['orderData'] : [$decoded['orderData']];
    } elseif (isset($decoded['orderList']['orderData'])) {
        $orders = is_array($decoded['orderList']['orderData']) ? $decoded['orderList']['orderData'] : [$decoded['orderList']['orderData']];
    } elseif (is_array($decoded)) {
        $orders = $decoded;
    }

    // Helper to safely extract string from CB API value (may be object)
    $safe = function($val, $fallback = null) use (&$safe) {
        if (is_null($val)) return $fallback;
        if (is_string($val)) return $val;
        if (is_numeric($val)) return (string)$val;
        if (is_array($val)) {
            if (isset($val['value'])) return (string)$val['value'];
            if (isset($val['fullName'])) return $safe($val['fullName']);
            if (isset($val['email'])) return $safe($val['email']);
            $keys = array_keys($val);
            if (count($keys) === 1) return $safe($val[$keys[0]]);
            return $fallback;
        }
        return (string)$val;
    };

    $sql = "INSERT INTO orders (domain_id, order_id, date_created, status, customer_name, customer_email, customer_phone,
            address, city, state, country, zip, affiliate_id, affiliate_name, product_names, product_codenames,
            payment_method, total_amount, vendor_net, flag_upsell, is_test, ip_address, external_order_id, order_details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            date_created = VALUES(date_created), status = VALUES(status),
            customer_name = VALUES(customer_name), customer_email = VALUES(customer_email),
            customer_phone = VALUES(customer_phone), address = VALUES(address),
            city = VALUES(city), state = VALUES(state), country = VALUES(country), zip = VALUES(zip),
            affiliate_id = VALUES(affiliate_id), affiliate_name = VALUES(affiliate_name),
            product_names = VALUES(product_names), product_codenames = VALUES(product_codenames),
            payment_method = VALUES(payment_method), total_amount = VALUES(total_amount),
            vendor_net = VALUES(vendor_net), flag_upsell = VALUES(flag_upsell),
            is_test = VALUES(is_test), ip_address = VALUES(ip_address),
            external_order_id = VALUES(external_order_id), order_details = VALUES(order_details)";

    $stmt = $pdo->prepare($sql);

    $synced = 0;
    $skipped = 0;

    foreach ($orders as $o) {
        $receipt = $safe($o['receipt'] ?? null);
        if (!$receipt) { $skipped++; continue; }

        $txnType = strtoupper($safe($o['txnType'] ?? $o['transactionType'] ?? '', ''));
        $isTest = (strpos($txnType, 'TEST') !== false) ? 1 : 0;

        // Skip test orders — only sync real/active orders
        if ($isTest) { $skipped++; continue; }

        // Customer info — CB API nests under customer.billing / customer.shipping
        // but may also have flat customer fields or wrapped objects
        $customer = (isset($o['customer']) && is_array($o['customer'])) ? $o['customer'] : [];
        $billing = (isset($customer['billing']) && is_array($customer['billing'])) ? $customer['billing'] : [];
        $shipping = (isset($customer['shipping']) && is_array($customer['shipping'])) ? $customer['shipping'] : [];

        // Helper: try billing → shipping → customer → order-level for a field
        $custField = function($keys) use ($billing, $shipping, $customer, $o, $safe) {
            foreach ($keys as $k) {
                $v = $safe($billing[$k] ?? null);
                if ($v) return $v;
                $v = $safe($shipping[$k] ?? null);
                if ($v) return $v;
                $v = $safe($customer[$k] ?? null);
                if ($v) return $v;
                $v = $safe($o[$k] ?? null);
                if ($v) return $v;
            }
            return null;
        };

        $name = $custField(['fullName', 'customerFullName', 'firstName']);
        $email = $custField(['email', 'customerEmail']);
        $phone = $custField(['phoneNumber', 'phone']);
        $address = $custField(['address1', 'address', 'street1', 'streetAddress']);
        $city = $custField(['city']);
        $state = $custField(['state', 'province', 'stateProvince']);
        $country = $custField(['country']);
        $zip = $custField(['postalCode', 'zip', 'zipCode']);

        $affiliate = $safe($o['affiliate'] ?? $o['affi'] ?? null);
        $vendorNick = $safe($o['vendor'] ?? $o['vendorNickname'] ?? null);

        // Line items — build product names + codenames, sum amounts
        // CB API nests: lineItemData can be {lineItemData: [...]} or [{...}] or {single item}
        $lineItems = $o['lineItemData'] ?? $o['lineItems'] ?? [];
        if (is_array($lineItems) && isset($lineItems['lineItemData'])) {
            $lineItems = is_array($lineItems['lineItemData']) ? $lineItems['lineItemData'] : [$lineItems['lineItemData']];
        }
        if (!is_array($lineItems)) $lineItems = [$lineItems];
        // If it's an associative array (single item, not list), wrap it
        if (!empty($lineItems) && isset($lineItems['itemNo'])) $lineItems = [$lineItems];
        if (empty($lineItems)) $lineItems = [[]];

        $productNames = [];
        $productCodes = [];
        $totalAmount = 0;
        $vendorNet = 0;
        $payMethod = null;
        $hasUpsell = false;

        foreach ($lineItems as $li) {
            $itemTitle = $safe($li['productTitle'] ?? null);
            $itemNo = $safe($li['itemNo'] ?? null);
            $liType = strtoupper($safe($li['lineItemType'] ?? '', ''));

            $displayName = $itemTitle ?: $itemNo;
            if ($displayName && $liType === 'BUMP') $displayName .= ' [BUMP]';
            if ($displayName) $productNames[] = $displayName;
            if ($itemNo) $productCodes[] = $itemNo;

            $custAmt = floatval($safe($li['customerAmount'] ?? $li['amount'] ?? null, '0'));
            $acctAmt = floatval($safe($li['accountAmount'] ?? null, '0'));
            $totalAmount += $custAmt;
            $vendorNet += $acctAmt;

            if (!$payMethod) $payMethod = $safe($li['paymentMethod'] ?? null);
            if ($liType === 'UPSELL') $hasUpsell = true;
        }

        // Use order-level amounts as fallback
        if ($totalAmount == 0) $totalAmount = floatval($safe($o['totalOrderAmount'] ?? $o['totalAmount'] ?? null, '0'));
        if ($vendorNet == 0) $vendorNet = floatval($safe($o['vendorAmount'] ?? null, '0'));
        if (!$payMethod) $payMethod = $safe($o['paymentMethod'] ?? null);

        // Date
        $dateCreated = $safe($o['transactionTime'] ?? $o['date'] ?? null);
        if ($dateCreated) {
            // CB dates: "2026-03-14T01:33:00-07:00" or similar
            $ts = strtotime($dateCreated);
            $dateCreated = $ts ? date('Y-m-d H:i:s', $ts) : null;
        }

        // Status
        $status = $txnType ?: 'SALE';

        // Match IP from shaver traffic: find affiliate_traffic record with same
        // domain + affiliate within 7 days before the order date
        $matchedIp = null;
        if ($affiliate && $dateCreated) {
            $ipStmt = $pdo->prepare("
                SELECT ip_address, ipqs_raw, country, country_code FROM affiliate_traffic
                WHERE domain_id = ? AND LOWER(aff_id) = LOWER(?)
                  AND page_type = 'landing'
                  AND timestamp BETWEEN DATE_SUB(?, INTERVAL 7 DAY) AND ?
                ORDER BY timestamp DESC LIMIT 1
            ");
            $ipStmt->execute([$domainId, $affiliate, $dateCreated, $dateCreated]);
            $ipRow = $ipStmt->fetch();
            if ($ipRow) {
                $matchedIp = $ipRow['ip_address'];

                // Fill missing geo fields individually from IPQS geolocation
                if ((!$city || !$state || !$country || !$zip) && !empty($ipRow['ipqs_raw'])) {
                    $ipqs = json_decode($ipRow['ipqs_raw'], true);
                    if ($ipqs) {
                        if (!$city) $city = $ipqs['city'] ?? null;
                        if (!$state) $state = $ipqs['region'] ?? null;
                        if (!$country) $country = $ipqs['country_code'] ?? null;
                        if (!$zip) $zip = $ipqs['zip_code'] ?? null;
                    }
                }
                // Fallback: use traffic record's own country column
                if (!$country && !empty($ipRow['country'])) {
                    $country = $ipRow['country'];
                }

                // Also try other traffic records if this one had no IPQS
                if ((!$city || !$state || !$country || !$zip) && $matchedIp) {
                    $geoStmt = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL LIMIT 1");
                    $geoStmt->execute([$matchedIp]);
                    $geoRow = $geoStmt->fetch();
                    if ($geoRow) {
                        $ipqs = json_decode($geoRow['ipqs_raw'], true);
                        if ($ipqs) {
                            if (!$city) $city = $ipqs['city'] ?? null;
                            if (!$state) $state = $ipqs['region'] ?? null;
                            if (!$country) $country = $ipqs['country_code'] ?? null;
                            if (!$zip) $zip = $ipqs['zip_code'] ?? null;
                        }
                    }
                }
            }
        }

        // Store raw customer JSON in order_details for debugging
        $orderDetails = json_encode($customer ?: ($o['customer'] ?? null));

        $stmt->execute([
            $domainId, $receipt, $dateCreated, $status, $name, $email, $phone,
            $address, $city, $state, $country, $zip,
            $affiliate, null, // affiliate_name not in CB API
            implode(', ', $productNames) ?: null,
            implode(', ', $productCodes) ?: null,
            $payMethod, $totalAmount, $vendorNet,
            $hasUpsell ? 1 : 0, $isTest, $matchedIp,
            $safe($o['transactionId'] ?? null), // external_order_id
            $orderDetails // raw customer JSON for debugging
        ]);

        $synced++;
    }

    // Enrich with shipping addresses from shipping2/list API
    $shippingEnriched = 0;
    $shippingUrl = 'https://api.clickbank.com/rest/1.3/shipping2/list?startDate=' . urlencode($startDate) . '&endDate=' . urlencode($endDate);
    $sCh = curl_init($shippingUrl);
    curl_setopt_array($sCh, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: ' . CB_API_KEY
        ],
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $sResponse = curl_exec($sCh);
    $sHttpCode = curl_getinfo($sCh, CURLINFO_HTTP_CODE);
    curl_close($sCh);

    if ($sHttpCode === 200 && !empty(trim($sResponse))) {
        $sResponse = preg_replace('/^\xEF\xBB\xBF/', '', trim($sResponse));
        $sDecoded = json_decode($sResponse, true);
        if ($sDecoded === null) {
            $xmlStart = strpos($sResponse, '<');
            $xmlContent = $xmlStart !== false ? substr($sResponse, $xmlStart) : $sResponse;
            $xml = @simplexml_load_string($xmlContent);
            if ($xml) $sDecoded = json_decode(json_encode($xml), true);
        }

        $shipments = [];
        if (isset($sDecoded['orderShipData'])) {
            $shipments = is_array($sDecoded['orderShipData']) ? $sDecoded['orderShipData'] : [$sDecoded['orderShipData']];
        } elseif (isset($sDecoded['shippingData'])) {
            $shipments = is_array($sDecoded['shippingData']) ? $sDecoded['shippingData'] : [$sDecoded['shippingData']];
        } elseif (isset($sDecoded['shippingList']['shippingData'])) {
            $shipments = is_array($sDecoded['shippingList']['shippingData']) ? $sDecoded['shippingList']['shippingData'] : [$sDecoded['shippingList']['shippingData']];
        }

        $updStmt = $pdo->prepare("UPDATE orders SET address = COALESCE(NULLIF(address,''), ?), city = COALESCE(NULLIF(city,''), ?), state = COALESCE(NULLIF(state,''), ?), country = COALESCE(NULLIF(country,''), ?), zip = COALESCE(NULLIF(zip,''), ?), customer_phone = COALESCE(NULLIF(customer_phone,''), ?) WHERE domain_id = ? AND order_id = ?");

        foreach ($shipments as $sh) {
            $sReceipt = $safe($sh['receipt'] ?? null);
            if (!$sReceipt) continue;

            $sAddr = $safe($sh['address1'] ?? null);
            $sAddr2 = $safe($sh['address2'] ?? null);
            if ($sAddr && $sAddr2) $sAddr .= ', ' . $sAddr2;
            $sCity = $safe($sh['city'] ?? null);
            $sState = $safe($sh['state'] ?? $sh['province'] ?? null);
            $sCountry = $safe($sh['country'] ?? null);
            $sZip = $safe($sh['postalCode'] ?? $sh['zip'] ?? null);
            $sPhone = $safe($sh['phoneNumber'] ?? $sh['phone'] ?? null);

            $updStmt->execute([$sAddr, $sCity, $sState, $sCountry, $sZip, $sPhone, $domainId, $sReceipt]);
            if ($updStmt->rowCount() > 0) $shippingEnriched++;
        }
    }

    echo json_encode([
        'success' => true,
        'synced' => $synced,
        'skipped' => $skipped,
        'shipping_enriched' => $shippingEnriched,
        'total_fetched' => count($orders),
        'message' => "Synced $synced orders from ClickBank" . ($shippingEnriched > 0 ? ", enriched $shippingEnriched with shipping addresses" : "")
    ]);
}

// ================================================================
// ANALYTICS ENDPOINTS
// ================================================================

function getAnalytics($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $period = $data['period'] ?? $_GET['period'] ?? 'today';

    $timeFilter = getTimeFilter($period);
    $domainFilter = !empty($domainId) ? " AND domain_id = " . (int)$domainId : "";

    // Build advanced filter conditions (parameterized)
    $extraWhere = '';
    $filterParams = [];
    $affId = $data['aff_id'] ?? '';
    if (!empty($affId)) { $extraWhere .= " AND aff_id = ?"; $filterParams[] = $affId; }
    $landingPage = $data['landing_page'] ?? '';
    if (!empty($landingPage)) { $extraWhere .= " AND page_url LIKE ?"; $filterParams[] = '%' . $landingPage . '%'; }
    $scrollDepth = $data['scroll_depth'] ?? '';
    if ($scrollDepth === 'low') $extraWhere .= " AND (max_scroll_depth IS NULL OR max_scroll_depth < 25)";
    elseif ($scrollDepth === 'medium') $extraWhere .= " AND max_scroll_depth BETWEEN 25 AND 74";
    elseif ($scrollDepth === 'high') $extraWhere .= " AND max_scroll_depth >= 75";
    $checkoutStatus = $data['checkout_status'] ?? '';
    if ($checkoutStatus !== '') { $extraWhere .= " AND reached_checkout = ?"; $filterParams[] = ($checkoutStatus === 'yes') ? 1 : 0; }
    $pageTypeFilter = $data['page_type'] ?? '';
    if (!empty($pageTypeFilter)) { $extraWhere .= " AND page_type = ?"; $filterParams[] = $pageTypeFilter; }

    $baseWhere = "$timeFilter $domainFilter $extraWhere";

    // Total stats
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_visits,
            COUNT(DISTINCT aff_id) as unique_affiliates,
            SUM(was_shaved) as shaved_visits,
            COUNT(DISTINCT ip_address) as unique_visitors,
            AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
            AVG(NULLIF(session_duration, 0)) as avg_session_duration,
            SUM(COALESCE(total_clicks, 0)) as total_clicks,
            SUM(COALESCE(redirect_clicks, 0)) as total_redirect_clicks,
            SUM(COALESCE(buynow_clicks, 0)) as total_buynow_clicks,
            SUM(COALESCE(footer_clicks, 0)) as total_footer_clicks,
            SUM(COALESCE(video_plays, 0)) as total_video_plays,
            SUM(COALESCE(cta_bar_clicks, 0)) as total_cta_bar_clicks,
            SUM(COALESCE(vsl_clicks, 0)) as total_vsl_clicks,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout_count,
            SUM(CASE WHEN matched_order_id IS NOT NULL AND matched_order_id != '' THEN 1 ELSE 0 END) as confirmed_orders,
            SUM(CASE WHEN session_duration > 5 THEN 1 ELSE 0 END) as qualified_visits,
            SUM(bounce) as bounce_count,
            AVG(NULLIF(redirect_time_ms, 0)) as avg_redirect_time_ms
        FROM affiliate_traffic
        WHERE $baseWhere
    ");
    $stmt->execute($filterParams);
    $totals = $stmt->fetch();

    // Top affiliates
    $stmt = $pdo->prepare("
        SELECT aff_id, COUNT(*) as visits, SUM(was_shaved) as shaved, COUNT(DISTINCT ip_address) as unique_ips,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as checkouts,
            SUM(COALESCE(buynow_clicks, 0)) as total_buynow
        FROM affiliate_traffic
        WHERE $baseWhere
        GROUP BY aff_id ORDER BY visits DESC LIMIT 20
    ");
    $stmt->execute($filterParams);
    $topAffiliates = $stmt->fetchAll();

    // Top landing pages per affiliate (top 3 each)
    $affLandingPages = [];
    if (!empty($topAffiliates)) {
        $affIds = array_column($topAffiliates, 'aff_id');
        $placeholders = implode(',', array_fill(0, count($affIds), '?'));
        $lpParams = array_merge($filterParams, $affIds);
        $stmt = $pdo->prepare("
            SELECT aff_id,
                CONCAT('/',
                    SUBSTRING_INDEX(
                        SUBSTRING(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''),
                            LENGTH(SUBSTRING_INDEX(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''), '/', 1)) + 2
                        ), '?', 1
                    )
                ) as page_path,
                COUNT(*) as cnt
            FROM affiliate_traffic
            WHERE $baseWhere AND aff_id IN ($placeholders) AND page_url IS NOT NULL AND page_url != ''
            GROUP BY aff_id, page_path ORDER BY aff_id, cnt DESC
        ");
        $stmt->execute($lpParams);
        foreach ($stmt->fetchAll() as $row) {
            $aid = $row['aff_id'];
            if (!isset($affLandingPages[$aid])) $affLandingPages[$aid] = [];
            if (count($affLandingPages[$aid]) < 3) {
                $affLandingPages[$aid][] = ['page' => $row['page_path'], 'count' => (int)$row['cnt']];
            }
        }
    }

    // Browser breakdown
    $stmt = $pdo->prepare("
        SELECT browser, COUNT(*) as count FROM affiliate_traffic
        WHERE $baseWhere AND browser IS NOT NULL AND browser != ''
        GROUP BY browser ORDER BY count DESC LIMIT 15
    ");
    $stmt->execute($filterParams);
    $browsers = $stmt->fetchAll();

    // Device breakdown
    $stmt = $pdo->prepare("
        SELECT device, COUNT(*) as count FROM affiliate_traffic
        WHERE $baseWhere AND device IS NOT NULL AND device != ''
        GROUP BY device ORDER BY count DESC
    ");
    $stmt->execute($filterParams);
    $devices = $stmt->fetchAll();

    // Country breakdown
    $stmt = $pdo->prepare("
        SELECT country, country_code, COUNT(*) as count FROM affiliate_traffic
        WHERE $baseWhere AND country IS NOT NULL AND country != ''
        GROUP BY country, country_code ORDER BY count DESC LIMIT 15
    ");
    $stmt->execute($filterParams);
    $countries = $stmt->fetchAll();

    // Top referrers — full path (no protocol, no query string)
    $stmt = $pdo->prepare("
        SELECT
            CASE WHEN referrer = '' OR referrer IS NULL OR referrer = 'direct' THEN 'Direct'
            ELSE SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '?', 1)
            END as source,
            COUNT(*) as count
        FROM affiliate_traffic
        WHERE $baseWhere
        GROUP BY source ORDER BY count DESC LIMIT 50
    ");
    $stmt->execute($filterParams);
    $referrers = $stmt->fetchAll();

    // Enrich referrers with top affiliate + landing page per source (batch queries)
    $stmt = $pdo->prepare("
        SELECT
            CASE WHEN referrer = '' OR referrer IS NULL OR referrer = 'direct' THEN 'Direct'
            ELSE SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '?', 1)
            END as source,
            aff_id, COUNT(*) as cnt
        FROM affiliate_traffic
        WHERE $baseWhere AND aff_id IS NOT NULL AND aff_id != ''
        GROUP BY source, aff_id ORDER BY source, cnt DESC
    ");
    $stmt->execute($filterParams);
    $affBySource = [];
    foreach ($stmt->fetchAll() as $row) {
        $s = $row['source'];
        if (!isset($affBySource[$s])) $affBySource[$s] = $row['aff_id'];
    }
    // Top landing page per source
    $stmt = $pdo->prepare("
        SELECT
            CASE WHEN referrer = '' OR referrer IS NULL OR referrer = 'direct' THEN 'Direct'
            ELSE SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '?', 1)
            END as source,
            CONCAT('/', SUBSTRING_INDEX(
                SUBSTRING(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''),
                    LENGTH(SUBSTRING_INDEX(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''), '/', 1)) + 2
                ), '?', 1
            )) as page_path,
            COUNT(*) as cnt
        FROM affiliate_traffic
        WHERE $baseWhere AND page_url IS NOT NULL AND page_url != ''
        GROUP BY source, page_path ORDER BY source, cnt DESC
    ");
    $stmt->execute($filterParams);
    $pageBySource = [];
    foreach ($stmt->fetchAll() as $row) {
        $s = $row['source'];
        if (!isset($pageBySource[$s])) $pageBySource[$s] = $row['page_path'];
    }
    // Get affiliate names in batch
    $affIds = array_unique(array_values($affBySource));
    $affNameMap = [];
    if (!empty($affIds) && !empty($domainId)) {
        $ph = implode(',', array_fill(0, count($affIds), '?'));
        $stmtN = $pdo->prepare("SELECT aff_id, name FROM affiliate_names WHERE domain_id = ? AND aff_id IN ($ph)");
        $stmtN->execute(array_merge([(int)$domainId], $affIds));
        foreach ($stmtN->fetchAll() as $row) { $affNameMap[$row['aff_id']] = $row['name']; }
    }
    foreach ($referrers as &$ref) {
        $aid = $affBySource[$ref['source']] ?? null;
        $ref['aff_id'] = $aid;
        $ref['aff_name'] = $aid ? ($affNameMap[$aid] ?? '') : null;
        $ref['landing_page'] = $pageBySource[$ref['source']] ?? null;
    }
    unset($ref);

    // Landing pages — full path after domain (no domain shown), with sample URL for linking
    $stmt = $pdo->prepare("
        SELECT
            CONCAT('/',
                SUBSTRING_INDEX(
                    SUBSTRING(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''),
                        LENGTH(SUBSTRING_INDEX(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''), '/', 1)) + 2
                    ), '?', 1
                )
            ) as landing_page,
            MIN(SUBSTRING_INDEX(page_url, '?', 1)) as sample_url,
            COUNT(*) as count
        FROM affiliate_traffic
        WHERE $baseWhere AND page_url IS NOT NULL AND page_url != ''
        GROUP BY landing_page ORDER BY count DESC LIMIT 15
    ");
    $stmt->execute($filterParams);
    $landingPages = $stmt->fetchAll();

    // Package breakdown — extract product_codename from checkout URLs
    $stmt = $pdo->prepare("
        SELECT
            CASE
                WHEN checkout_url LIKE '%upsell%' THEN 'upsell'
                WHEN checkout_url REGEXP 'product_codename=([^&]+)' THEN
                    SUBSTRING_INDEX(SUBSTRING_INDEX(checkout_url, 'product_codename=', -1), '&', 1)
                WHEN checkout_url LIKE '%offer-details%' OR checkout_url LIKE '%backoffice.buygoods%' THEN 'bg_offer_view'
                ELSE 'other'
            END as product_code,
            COUNT(*) as count
        FROM affiliate_traffic
        WHERE $baseWhere AND checkout_url IS NOT NULL AND checkout_url != ''
        GROUP BY product_code ORDER BY count DESC
    ");
    $stmt->execute($filterParams);
    $packages = $stmt->fetchAll();

    // Affiliates Exploring the Offer — visits to afftools page OR aff_id = 'zzzzz'
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as aff_exploring,
            COUNT(*) - COUNT(DISTINCT ip_address) as recurring_aff_exploring
        FROM affiliate_traffic
        WHERE $baseWhere AND (page_url LIKE '%aff/aff_details/aff/afftools%' OR LOWER(aff_id) = 'zzzzz')
    ");
    $stmt->execute($filterParams);
    $affExploring = $stmt->fetch();

    // Recurring affiliates exploring = IPs that visited afftools page or zzzzz more than once
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as recurring_count FROM (
            SELECT ip_address FROM affiliate_traffic
            WHERE $baseWhere AND (page_url LIKE '%aff/aff_details/aff/afftools%' OR LOWER(aff_id) = 'zzzzz')
            GROUP BY ip_address HAVING COUNT(*) > 1
        ) as recurring
    ");
    $stmt->execute($filterParams);
    $recurringAffExploring = (int)$stmt->fetchColumn();

    // Traffic breakdown by category
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN page_url LIKE '%aff/aff_details/aff/afftools%' OR LOWER(aff_id) = 'zzzzz' THEN 1 ELSE 0 END) as aff_exploring_visits,
            SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_visits,
            SUM(CASE WHEN (aff_id IS NULL OR aff_id = '') AND is_bot = 0 AND (page_url NOT LIKE '%aff/aff_details/aff/afftools%' OR page_url IS NULL) AND LOWER(COALESCE(aff_id,'')) != 'zzzzz' THEN 1 ELSE 0 END) as direct_visits,
            SUM(CASE WHEN aff_id IS NOT NULL AND aff_id != '' AND LOWER(aff_id) != 'zzzzz' AND is_bot = 0 AND (page_url NOT LIKE '%aff/aff_details/aff/afftools%' OR page_url IS NULL) THEN 1 ELSE 0 END) as affiliate_visits
        FROM affiliate_traffic
        WHERE $baseWhere
    ");
    $stmt->execute($filterParams);
    $breakdown = $stmt->fetch();

    // Top 3 affiliates with their top page for the breakdown section
    $stmt = $pdo->prepare("
        SELECT aff_id, COUNT(*) as visits,
            COUNT(DISTINCT ip_address) as unique_ips,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as checkouts,
            SUM(bounce) as bounces
        FROM affiliate_traffic
        WHERE $baseWhere AND aff_id IS NOT NULL AND aff_id != '' AND LOWER(aff_id) != 'zzzzz' AND is_bot = 0
            AND (page_url NOT LIKE '%aff/aff_details/aff/afftools%' OR page_url IS NULL)
        GROUP BY aff_id ORDER BY visits DESC LIMIT 3
    ");
    $stmt->execute($filterParams);
    $top3Affiliates = $stmt->fetchAll();

    // Get affiliate names for top 3
    $affNameMap = [];
    if (!empty($top3Affiliates) && !empty($domainId)) {
        $affIds3 = array_column($top3Affiliates, 'aff_id');
        $ph3 = implode(',', array_fill(0, count($affIds3), '?'));
        $stmtNames = $pdo->prepare("SELECT aff_id, name FROM affiliate_names WHERE domain_id = ? AND aff_id IN ($ph3)");
        $stmtNames->execute(array_merge([(int)$domainId], $affIds3));
        foreach ($stmtNames->fetchAll() as $row) {
            $affNameMap[$row['aff_id']] = $row['name'];
        }
    }

    // Get top page for each of the top 3
    $top3WithPages = [];
    foreach ($top3Affiliates as $aff) {
        $stmtPage = $pdo->prepare("
            SELECT CONCAT('/',
                SUBSTRING_INDEX(
                    SUBSTRING(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''),
                        LENGTH(SUBSTRING_INDEX(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''), '/', 1)) + 2
                    ), '?', 1
                )
            ) as page_path, COUNT(*) as cnt
            FROM affiliate_traffic
            WHERE $baseWhere AND aff_id = ? AND page_url IS NOT NULL AND page_url != ''
            GROUP BY page_path ORDER BY cnt DESC LIMIT 1
        ");
        $stmtPage->execute(array_merge($filterParams, [$aff['aff_id']]));
        $topPage = $stmtPage->fetch();
        $top3WithPages[] = [
            'aff_id' => $aff['aff_id'],
            'aff_name' => $affNameMap[$aff['aff_id']] ?? '',
            'visits' => (int)$aff['visits'],
            'unique_ips' => (int)$aff['unique_ips'],
            'checkouts' => (int)$aff['checkouts'],
            'bounces' => (int)$aff['bounces'],
            'top_page' => $topPage ? $topPage['page_path'] : '/',
            'top_page_visits' => $topPage ? (int)$topPage['cnt'] : 0
        ];
    }

    // Bot flags breakdown — top reasons
    $stmt = $pdo->prepare("
        SELECT bot_flags, COUNT(*) as cnt
        FROM affiliate_traffic
        WHERE $baseWhere AND is_bot = 1 AND bot_flags IS NOT NULL AND bot_flags != ''
        GROUP BY bot_flags ORDER BY cnt DESC LIMIT 10
    ");
    $stmt->execute($filterParams);
    $botFlagsRaw = $stmt->fetchAll();
    $botReasons = [];
    foreach ($botFlagsRaw as $row) {
        $botReasons[] = ['reason' => $row['bot_flags'], 'count' => (int)$row['cnt']];
    }

    // Top 3 countries for affiliate traffic
    $stmt = $pdo->prepare("
        SELECT country, COUNT(*) as cnt
        FROM affiliate_traffic
        WHERE $baseWhere AND aff_id IS NOT NULL AND aff_id != '' AND LOWER(aff_id) != 'zzzzz'
            AND is_bot = 0 AND country IS NOT NULL AND country != ''
            AND (page_url NOT LIKE '%aff/aff_details/aff/afftools%' OR page_url IS NULL)
        GROUP BY country ORDER BY cnt DESC LIMIT 3
    ");
    $stmt->execute($filterParams);
    $topAffCountries = array_map(function($r) { return ['country' => $r['country'], 'count' => (int)$r['cnt']]; }, $stmt->fetchAll());

    $totalVisits = (int)$totals['total_visits'];
    $shavedVisits = (int)($totals['shaved_visits'] ?? 0);
    $bounceCount = (int)($totals['bounce_count'] ?? 0);
    $reachedCheckoutCount = (int)($totals['reached_checkout_count'] ?? 0);
    $qualifiedVisits = (int)($totals['qualified_visits'] ?? 0);

    // Format affiliates with 'count' key for frontend
    $formattedAffiliates = array_map(function($a) use ($affLandingPages) {
        return [
            'aff_id' => $a['aff_id'],
            'count' => (int)$a['visits'],
            'shaved' => (int)$a['shaved'],
            'unique_ips' => (int)$a['unique_ips'],
            'checkouts' => (int)($a['checkouts'] ?? 0),
            'pages' => $affLandingPages[$a['aff_id']] ?? []
        ];
    }, $topAffiliates);

    // Previous period comparison
    $prevData = null;
    $prevTimeFilter = getPreviousPeriodFilter($period);
    if ($prevTimeFilter) {
        $prevWhere = "$prevTimeFilter $domainFilter $extraWhere";
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_visits,
                COUNT(DISTINCT aff_id) as unique_affiliates,
                SUM(was_shaved) as shaved_visits,
                AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
                AVG(NULLIF(session_duration, 0)) as avg_session_duration,
                SUM(COALESCE(redirect_clicks, 0)) as total_redirect_clicks,
                SUM(COALESCE(buynow_clicks, 0)) as total_buynow_clicks,
                SUM(COALESCE(video_plays, 0)) as total_video_plays,
                SUM(COALESCE(cta_bar_clicks, 0)) as total_cta_bar_clicks,
                SUM(COALESCE(vsl_clicks, 0)) as total_vsl_clicks,
                SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout_count,
                SUM(CASE WHEN matched_order_id IS NOT NULL AND matched_order_id != '' THEN 1 ELSE 0 END) as confirmed_orders,
                SUM(CASE WHEN session_duration > 5 THEN 1 ELSE 0 END) as qualified_visits,
                SUM(bounce) as bounce_count
            FROM affiliate_traffic
            WHERE $prevWhere
        ");
        $stmt->execute($filterParams);
        $prev = $stmt->fetch();

        $prevTotal = (int)$prev['total_visits'];
        $prevBounce = (int)($prev['bounce_count'] ?? 0);
        $prevQualified = (int)($prev['qualified_visits'] ?? 0);
        $prevCheckout = (int)($prev['reached_checkout_count'] ?? 0);

        // Aff exploring previous
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM affiliate_traffic WHERE $prevWhere AND page_url LIKE '%aff/aff_details/aff/afftools%'");
        $stmt->execute($filterParams);
        $prevAffExploring = (int)$stmt->fetchColumn();

        $prevData = [
            'totalVisits' => $prevTotal,
            'shavedVisits' => (int)($prev['shaved_visits'] ?? 0),
            'uniqueAffiliates' => (int)$prev['unique_affiliates'],
            'avgScrollDepth' => round((float)($prev['avg_scroll_depth'] ?? 0), 1),
            'avgSessionDuration' => round((float)($prev['avg_session_duration'] ?? 0), 1),
            'redirectClicks' => (int)($prev['total_redirect_clicks'] ?? 0),
            'buynowClicks' => (int)($prev['total_buynow_clicks'] ?? 0),
            'videoPlays' => (int)($prev['total_video_plays'] ?? 0),
            'ctaBarClicks' => (int)($prev['total_cta_bar_clicks'] ?? 0),
            'vslClicks' => (int)($prev['total_vsl_clicks'] ?? 0),
            'checkoutRate' => $prevQualified > 0 ? round(($prevCheckout / $prevQualified) * 100, 1) : 0,
            'confirmedOrders' => (int)($prev['confirmed_orders'] ?? 0),
            'bounceRate' => $prevTotal > 0 ? round(($prevBounce / $prevTotal) * 100, 1) : 0,
            'affExploring' => $prevAffExploring
        ];
    }

    echo json_encode([
        'success' => true,
        'totalVisits' => $totalVisits,
        'shavedVisits' => $shavedVisits,
        'uniqueAffiliates' => (int)$totals['unique_affiliates'],
        'uniqueVisitors' => (int)$totals['unique_visitors'],
        'avgScrollDepth' => round((float)($totals['avg_scroll_depth'] ?? 0), 1),
        'avgSessionDuration' => round((float)($totals['avg_session_duration'] ?? 0), 1),
        'redirectClicks' => (int)($totals['total_redirect_clicks'] ?? 0),
        'buynowClicks' => (int)($totals['total_buynow_clicks'] ?? 0),
        'footerClicks' => (int)($totals['total_footer_clicks'] ?? 0),
        'videoPlays' => (int)($totals['total_video_plays'] ?? 0),
        'ctaBarClicks' => (int)($totals['total_cta_bar_clicks'] ?? 0),
        'vslClicks' => (int)($totals['total_vsl_clicks'] ?? 0),
        'reachedCheckout' => $reachedCheckoutCount,
        'confirmedOrders' => (int)($totals['confirmed_orders'] ?? 0),
        'checkoutRate' => $qualifiedVisits > 0 ? round(($reachedCheckoutCount / $qualifiedVisits) * 100, 1) : 0,
        'qualifiedVisits' => $qualifiedVisits,
        'bounceRate' => $totalVisits > 0 ? round(($bounceCount / $totalVisits) * 100, 1) : 0,
        'totalClicks' => (int)($totals['total_clicks'] ?? 0),
        'avgRedirectTime' => round((float)($totals['avg_redirect_time_ms'] ?? 0) / 1000, 1),
        'topAffiliates' => $formattedAffiliates,
        'topBrowsers' => $browsers,
        'topDevices' => $devices,
        'topCountries' => $countries,
        'topReferrers' => $referrers,
        'topLandingPages' => $landingPages,
        'topPackages' => $packages,
        'affExploring' => (int)($affExploring['aff_exploring'] ?? 0),
        'recurringAffExploring' => $recurringAffExploring,
        'trafficBreakdown' => [
            'affiliate_visits' => (int)($breakdown['affiliate_visits'] ?? 0),
            'direct_visits' => (int)($breakdown['direct_visits'] ?? 0),
            'aff_exploring_visits' => (int)($breakdown['aff_exploring_visits'] ?? 0),
            'bot_visits' => (int)($breakdown['bot_visits'] ?? 0),
            'bot_reasons' => $botReasons
        ],
        'top3Affiliates' => $top3WithPages,
        'topAffCountries' => $topAffCountries,
        'prevPeriod' => $prevData
    ]);
}

function getFlowchartComparison($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? '';
    $domainFilter = !empty($domainId) ? " AND domain_id = " . (int)$domainId : "";
    $cutoff = '2026-03-23 13:00:00';

    // Mirror: Before period = same duration as After, counting back from cutoff
    $stmt = $pdo->query("SELECT GREATEST(TIMESTAMPDIFF(HOUR, '$cutoff', NOW()), 1) as hrs");
    $afterHours = (int)$stmt->fetchColumn();
    $beforeHours = $afterHours; // Same window size

    $beforeStart = date('Y-m-d H:i:s', strtotime($cutoff) - ($afterHours * 3600));
    $beforeStartLabel = date('M j g:i A', strtotime($beforeStart));

    $periods = [
        'before' => ["timestamp >= '$beforeStart' AND timestamp < '$cutoff'", $beforeStartLabel . ' – Mar 23 1:00 PM', $beforeHours],
        'after'  => ["timestamp >= '$cutoff'", 'Mar 23 1:00 PM – Now', $afterHours]
    ];

    $result = ['success' => true, 'cutoff' => $cutoff];

    foreach ($periods as $key => $period) {
        $baseWhere = "1=1 AND " . $period[0] . $domainFilter;
        $hours = $period[2] ?? $afterHours;

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_visits,
                COUNT(DISTINCT ip_address) as unique_visitors,
                AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
                AVG(NULLIF(session_duration, 0)) as avg_session_duration,
                SUM(COALESCE(total_clicks, 0)) as total_clicks,
                SUM(COALESCE(redirect_clicks, 0)) as total_redirect_clicks,
                SUM(COALESCE(buynow_clicks, 0)) as total_buynow_clicks,
                SUM(COALESCE(footer_clicks, 0)) as total_footer_clicks,
                SUM(COALESCE(video_plays, 0)) as total_video_plays,
                SUM(COALESCE(cta_bar_clicks, 0)) as total_cta_bar_clicks,
                SUM(COALESCE(vsl_clicks, 0)) as total_vsl_clicks,
                SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout_count,
                SUM(CASE WHEN session_duration > 5 THEN 1 ELSE 0 END) as qualified_visits,
                SUM(bounce) as bounce_count
            FROM affiliate_traffic
            WHERE $baseWhere
        ");
        $stmt->execute();
        $t = $stmt->fetch();

        $totalVisits = (int)$t['total_visits'];
        $qualifiedVisits = (int)($t['qualified_visits'] ?? 0);
        $bounceCount = (int)($t['bounce_count'] ?? 0);
        $reachedCheckout = (int)($t['reached_checkout_count'] ?? 0);

        // Device breakdown
        $stmt = $pdo->prepare("SELECT device, COUNT(*) as count FROM affiliate_traffic WHERE $baseWhere AND device IS NOT NULL AND device != '' GROUP BY device ORDER BY count DESC");
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Country breakdown
        $stmt = $pdo->prepare("SELECT country, country_code, COUNT(*) as count FROM affiliate_traffic WHERE $baseWhere AND country IS NOT NULL AND country != '' GROUP BY country, country_code ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Landing pages breakdown
        $stmt = $pdo->prepare("SELECT page_url as landing_page, COUNT(*) as count FROM affiliate_traffic WHERE $baseWhere AND page_url IS NOT NULL AND page_url != '' GROUP BY page_url ORDER BY count DESC LIMIT 10");
        $stmt->execute();
        $landingPages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Converter behavior — visitors who ended up ordering
        $converterWhere = $baseWhere . " AND (matched_order_id IS NOT NULL AND matched_order_id != '')";
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as converter_count,
                AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll,
                AVG(NULLIF(session_duration, 0)) as avg_duration,
                AVG(COALESCE(total_clicks, 0)) as avg_clicks,
                SUM(COALESCE(video_plays, 0)) as total_video,
                SUM(COALESCE(cta_bar_clicks, 0)) as total_cta,
                SUM(COALESCE(vsl_clicks, 0)) as total_vsl,
                SUM(COALESCE(buynow_clicks, 0)) as total_buynow
            FROM affiliate_traffic
            WHERE $converterWhere
        ");
        $stmt->execute();
        $cv = $stmt->fetch();
        $cvCount = (int)($cv['converter_count'] ?? 0);

        $converters = [
            'count' => $cvCount,
            'avgScroll' => round((float)($cv['avg_scroll'] ?? 0), 1),
            'avgDuration' => round((float)($cv['avg_duration'] ?? 0), 1),
            'avgClicks' => round((float)($cv['avg_clicks'] ?? 0), 1),
            'videoPlays' => $cvCount > 0 ? round((int)($cv['total_video'] ?? 0) / $cvCount, 1) : 0,
            'ctaClicks' => $cvCount > 0 ? round((int)($cv['total_cta'] ?? 0) / $cvCount, 1) : 0,
            'vslClicks' => $cvCount > 0 ? round((int)($cv['total_vsl'] ?? 0) / $cvCount, 1) : 0,
            'buynowClicks' => $cvCount > 0 ? round((int)($cv['total_buynow'] ?? 0) / $cvCount, 1) : 0,
            'conversionRate' => $totalVisits > 0 ? round(($cvCount / $totalVisits) * 100, 2) : 0
        ];

        $result[$key] = [
            'periodLabel' => $period[1],
            'hours' => $hours,
            'totalVisits' => $totalVisits,
            'uniqueVisitors' => (int)$t['unique_visitors'],
            'avgScrollDepth' => round((float)($t['avg_scroll_depth'] ?? 0), 1),
            'avgSessionDuration' => round((float)($t['avg_session_duration'] ?? 0), 1),
            'bounceRate' => $totalVisits > 0 ? round(($bounceCount / $totalVisits) * 100, 1) : 0,
            'videoPlays' => (int)($t['total_video_plays'] ?? 0),
            'ctaBarClicks' => (int)($t['total_cta_bar_clicks'] ?? 0),
            'vslClicks' => (int)($t['total_vsl_clicks'] ?? 0),
            'buynowClicks' => (int)($t['total_buynow_clicks'] ?? 0),
            'redirectClicks' => (int)($t['total_redirect_clicks'] ?? 0),
            'footerClicks' => (int)($t['total_footer_clicks'] ?? 0),
            'checkoutRate' => $qualifiedVisits > 0 ? round(($reachedCheckout / $qualifiedVisits) * 100, 1) : 0,
            'qualifiedVisits' => $qualifiedVisits,
            'totalClicks' => (int)($t['total_clicks'] ?? 0),
            'topDevices' => $devices,
            'topCountries' => $countries,
            'topLandingPages' => $landingPages,
            'converters' => $converters
        ];
    }

    echo json_encode($result);
}

function getTrafficLog($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $limit = min((int)($data['limit'] ?? $_GET['limit'] ?? 50), 200);
    $offset = (int)($data['offset'] ?? $_GET['offset'] ?? 0);
    $affId = $data['aff_id'] ?? $_GET['aff_id'] ?? '';
    $period = $data['period'] ?? $_GET['period'] ?? '';
    $landingPage = $data['landing_page'] ?? $_GET['landing_page'] ?? '';
    $scrollDepth = $data['scroll_depth'] ?? $_GET['scroll_depth'] ?? '';
    $checkoutStatus = $data['checkout_status'] ?? $_GET['checkout_status'] ?? '';
    $shavedStatus = $data['shaved_status'] ?? $_GET['shaved_status'] ?? '';

    $whereConditions = ["1=1"];
    $params = [];

    if (!empty($domainId)) {
        $whereConditions[] = "domain_id = ?";
        $params[] = $domainId;
    }
    if (!empty($affId)) {
        $whereConditions[] = "aff_id = ?";
        $params[] = $affId;
    }
    if (!empty($landingPage)) {
        $whereConditions[] = "page_url LIKE ?";
        $params[] = '%' . $landingPage . '%';
    }
    if ($scrollDepth !== '') {
        if ($scrollDepth === 'low') {
            $whereConditions[] = "(max_scroll_depth IS NULL OR max_scroll_depth < 25)";
        } elseif ($scrollDepth === 'medium') {
            $whereConditions[] = "max_scroll_depth BETWEEN 25 AND 74";
        } elseif ($scrollDepth === 'high') {
            $whereConditions[] = "max_scroll_depth >= 75";
        }
    }
    if ($checkoutStatus !== '') {
        $whereConditions[] = "reached_checkout = ?";
        $params[] = ($checkoutStatus === 'yes') ? 1 : 0;
    }
    if ($shavedStatus !== '') {
        if ($shavedStatus === 'smart_skip') {
            $whereConditions[] = "smart_skipped = 1";
        } else {
            $whereConditions[] = "was_shaved = ?";
            $params[] = ($shavedStatus === 'yes') ? 1 : 0;
        }
    }
    $vslOnly = $data['vsl_only'] ?? $_GET['vsl_only'] ?? '';
    if ($vslOnly === '1') {
        $whereConditions[] = "page_url LIKE '%vsl%'";
    }
    $ipFilter = $data['ip_address'] ?? $_GET['ip_address'] ?? '';
    if (!empty($ipFilter)) {
        $whereConditions[] = "ip_address LIKE ?";
        $params[] = '%' . $ipFilter . '%';
    }
    $pageTypeFilter = $data['page_type'] ?? $_GET['page_type'] ?? '';
    if (!empty($pageTypeFilter)) {
        $whereConditions[] = "page_type = ?";
        $params[] = $pageTypeFilter;
    } else {
        // Default: only show landing page rows (upsell/thankyou appear in funnel columns)
        $whereConditions[] = "page_type = 'landing'";
    }

    // Visitor status filter: clicked, confirmed_order
    $visitorStatus = $data['visitor_status'] ?? $_GET['visitor_status'] ?? '';
    if (!empty($visitorStatus)) {
        if ($visitorStatus === 'clicked') {
            $whereConditions[] = "total_clicks >= 1";
        } elseif ($visitorStatus === 'reached_checkout') {
            $whereConditions[] = "buynow_clicks >= 1";
        } elseif ($visitorStatus === 'redirect_clicked') {
            $whereConditions[] = "redirect_clicks >= 1";
        } elseif ($visitorStatus === 'cta_bar_clicked') {
            $whereConditions[] = "cta_bar_clicks >= 1";
        } elseif ($visitorStatus === 'vsl_clicked') {
            $whereConditions[] = "vsl_clicks >= 1";
        } elseif ($visitorStatus === 'video_played') {
            $whereConditions[] = "video_plays >= 1";
        } elseif ($visitorStatus === 'confirmed_order') {
            // Confirmed order = has matched_order_id OR reached upsell/thankyou page
            $whereConditions[] = "(
                matched_order_id IS NOT NULL AND matched_order_id != ''
                OR
                (sessid2 IS NOT NULL AND sessid2 != '' AND EXISTS (
                    SELECT 1 FROM affiliate_traffic at2 WHERE at2.sessid2 = affiliate_traffic.sessid2 AND at2.aff_id = affiliate_traffic.aff_id AND at2.page_type IN ('upsell','thankyou')
                ))
                OR
                (session_uuid IS NOT NULL AND session_uuid != '' AND EXISTS (
                    SELECT 1 FROM affiliate_traffic at3 WHERE at3.session_uuid = affiliate_traffic.session_uuid AND at3.aff_id = affiliate_traffic.aff_id AND at3.page_type IN ('upsell','thankyou')
                ))
            )";
        }
    }

    $where = implode(' AND ', $whereConditions);

    // Add period filter (uses raw SQL, not parameterized - safe since getTimeFilter generates it)
    if (!empty($period)) {
        $timeFilter = getTimeFilter($period);
        $where .= " AND $timeFilter";
    }

    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM affiliate_traffic WHERE $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['total'];

    $sql = "SELECT * FROM affiliate_traffic WHERE $where ORDER BY timestamp DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $traffic = $stmt->fetchAll();

    // Batch lookup: collect sessid2 values to find upsell/thankyou siblings
    $sessid2List = [];
    // Get domain_id from the first traffic item for scoping
    $currentDomainId = !empty($traffic) ? ($traffic[0]['domain_id'] ?? null) : null;
    foreach ($traffic as $item) {
        if (!empty($item['sessid2'])) {
            $sessid2List[$item['sessid2']] = true;
        }
    }
    $funnelMap = []; // sessid2 => ['upsells' => [...], 'thankyou' => [...]]
    if (!empty($sessid2List)) {
        try {
        $placeholders = implode(',', array_fill(0, count($sessid2List), '?'));
        // Bug fix: scope to same domain_id to prevent cross-domain upsell matching
        $domainFilter = $currentDomainId ? " AND domain_id = " . intval($currentDomainId) : "";
        $funnelStmt = $pdo->prepare("
            SELECT sessid2, aff_id, page_type, page_url, matched_order_id
            FROM affiliate_traffic
            WHERE sessid2 IN ($placeholders) AND page_type IN ('upsell', 'thankyou') $domainFilter
        ");
        $funnelStmt->execute(array_keys($sessid2List));
        $funnelRows = $funnelStmt->fetchAll();
        foreach ($funnelRows as $fr) {
            $sid = $fr['sessid2'];
            $fAff = $fr['aff_id'] ?? '';
            $key = $sid . '|' . $fAff;
            $keyGlobal = $sid . '|*'; // Global key to share matched order across affiliates for same session
            if (!isset($funnelMap[$key])) $funnelMap[$key] = ['upsells' => [], 'thankyou' => null, 'matchedOrderId' => null];
            if (!isset($funnelMap[$keyGlobal])) $funnelMap[$keyGlobal] = ['upsells' => [], 'thankyou' => null, 'matchedOrderId' => null];
            if ($fr['page_type'] === 'upsell') {
                // Extract upsell name from URL
                $upsellName = 'Upsell';
                $url = $fr['page_url'] ?? '';
                if (preg_match('/upsell\s*(\d+)/i', $url, $m)) {
                    $upsellName = 'Upsell ' . $m[1];
                } elseif (preg_match('/upsell/i', $url)) {
                    $upsellName = 'Upsell';
                }
                if (!in_array($upsellName, $funnelMap[$key]['upsells'])) {
                    $funnelMap[$key]['upsells'][] = $upsellName;
                }
                if (!in_array($upsellName, $funnelMap[$keyGlobal]['upsells'])) {
                    $funnelMap[$keyGlobal]['upsells'][] = $upsellName;
                }
                // Extract order_id_global from upsell URL (BuyGoods passes it)
                if (preg_match('/order_id_global=([^&]+)/', $url, $orderMatch)) {
                    $orderId = urldecode($orderMatch[1]);
                    if (empty($funnelMap[$key]['matchedOrderId'])) $funnelMap[$key]['matchedOrderId'] = $orderId;
                    if (empty($funnelMap[$keyGlobal]['matchedOrderId'])) $funnelMap[$keyGlobal]['matchedOrderId'] = $orderId;
                }
            } elseif ($fr['page_type'] === 'thankyou') {
                $funnelMap[$key]['thankyou'] = true;
                $funnelMap[$keyGlobal]['thankyou'] = true;
                if (!empty($fr['matched_order_id'])) {
                    $funnelMap[$key]['matchedOrderId'] = $fr['matched_order_id'];
                    $funnelMap[$keyGlobal]['matchedOrderId'] = $fr['matched_order_id'];
                }
            }
        }
        } catch (Exception $e) {
            // matched_order_id column may not exist yet
        }
    }

    // Fallback: for landing records without sessid2, use session_uuid to find funnel siblings
    $uuidMap = []; // session_uuid => ['upsells' => [...], 'thankyou' => null, 'matchedOrderId' => null]
    $uuidList = [];
    foreach ($traffic as $item) {
        if (empty($item['sessid2']) && !empty($item['session_uuid'])) {
            $uuidList[$item['session_uuid']] = true;
        }
    }
    if (!empty($uuidList)) {
        try {
            $placeholders = implode(',', array_fill(0, count($uuidList), '?'));
            $domainFilter2 = $currentDomainId ? " AND domain_id = " . intval($currentDomainId) : "";
            $uuidStmt = $pdo->prepare("
                SELECT session_uuid, aff_id, page_type, page_url, matched_order_id
                FROM affiliate_traffic
                WHERE session_uuid IN ($placeholders) AND page_type IN ('upsell', 'thankyou') $domainFilter2
            ");
            $uuidStmt->execute(array_keys($uuidList));
            $uuidRows = $uuidStmt->fetchAll();
            foreach ($uuidRows as $fr) {
                $uid = $fr['session_uuid'];
                $fAff = $fr['aff_id'] ?? '';
                $key = $uid . '|' . $fAff; // Key by session_uuid + aff_id to prevent cross-affiliate attribution
                if (!isset($uuidMap[$key])) $uuidMap[$key] = ['upsells' => [], 'thankyou' => null, 'matchedOrderId' => null];
                if ($fr['page_type'] === 'upsell') {
                    $upsellName = 'Upsell';
                    $url = $fr['page_url'] ?? '';
                    if (preg_match('/upsell\s*(\d+)/i', $url, $m)) {
                        $upsellName = 'Upsell ' . $m[1];
                    }
                    if (!in_array($upsellName, $uuidMap[$key]['upsells'])) {
                        $uuidMap[$key]['upsells'][] = $upsellName;
                    }
                    // Extract order_id_global from upsell URL
                    if (empty($uuidMap[$key]['matchedOrderId']) && preg_match('/order_id_global=([^&]+)/', $url, $orderMatch)) {
                        $uuidMap[$key]['matchedOrderId'] = urldecode($orderMatch[1]);
                    }
                } elseif ($fr['page_type'] === 'thankyou') {
                    $uuidMap[$key]['thankyou'] = true;
                    if (!empty($fr['matched_order_id'])) {
                        $uuidMap[$key]['matchedOrderId'] = $fr['matched_order_id'];
                    }
                }
            }
        } catch (Exception $e) {}
    }

    // Batch lookup shaving session details for shaved/skipped traffic
    $sessionIds = [];
    foreach ($traffic as $item) {
        if (!empty($item['shaving_session_id'])) $sessionIds[$item['shaving_session_id']] = true;
    }
    $sessionMap = [];
    if (!empty($sessionIds)) {
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $sStmt = $pdo->prepare("SELECT id, mode, shave_mode, replace_aff_id, replace_sub_id, cb_path_find, cb_path_replace FROM shaving_sessions WHERE id IN ($placeholders)");
        $sStmt->execute(array_keys($sessionIds));
        foreach ($sStmt->fetchAll() as $ss) { $sessionMap[$ss['id']] = $ss; }
        // Also check history for stopped sessions
        $hStmt = $pdo->prepare("SELECT session_id, mode, shave_mode FROM shaving_history WHERE session_id IN ($placeholders)");
        $hStmt->execute(array_keys($sessionIds));
        foreach ($hStmt->fetchAll() as $hh) {
            if (!isset($sessionMap[$hh['session_id']])) $sessionMap[$hh['session_id']] = $hh;
        }
    }

    // Backfill missing fraud scores from IP cache (same IP already scored on another record)
    $uncheckedIps = [];
    foreach ($traffic as $item) {
        if ($item['fraud_score'] === null && !empty($item['ip_address'])) {
            $uncheckedIps[$item['ip_address']] = true;
        }
    }
    if (!empty($uncheckedIps)) {
        $ph = implode(',', array_fill(0, count($uncheckedIps), '?'));
        $cacheStmt = $pdo->prepare("SELECT ip_address, fraud_score, fraud_risk_level, fraud_flags FROM affiliate_traffic WHERE ip_address IN ($ph) AND fraud_score IS NOT NULL GROUP BY ip_address ORDER BY MAX(timestamp) DESC");
        $cacheStmt->execute(array_keys($uncheckedIps));
        $ipFraudCache = [];
        foreach ($cacheStmt->fetchAll() as $cr) {
            $ipFraudCache[$cr['ip_address']] = $cr;
        }
        foreach ($traffic as &$item) {
            if ($item['fraud_score'] === null && !empty($item['ip_address']) && isset($ipFraudCache[$item['ip_address']])) {
                $c = $ipFraudCache[$item['ip_address']];
                $item['fraud_score'] = $c['fraud_score'];
                $item['fraud_risk_level'] = $c['fraud_risk_level'];
                $item['fraud_flags'] = $c['fraud_flags'];
                $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ? WHERE id = ? AND fraud_score IS NULL")
                    ->execute([$c['fraud_score'], $c['fraud_risk_level'], $c['fraud_flags'], $item['id']]);
            }
        }
        unset($item);
    }

    $formatted = array_map(function($item) use ($funnelMap, $uuidMap, $sessionMap) {
        $sid = $item['sessid2'] ?? null;
        $uid = $item['session_uuid'] ?? null;
        $aff = $item['aff_id'] ?? '';
        // Look up funnel: try affiliate-specific key first, then global key (cross-affiliate match for shaved sessions)
        $funnelKey1 = $sid ? ($sid . '|' . $aff) : null;
        $funnelKey2 = $uid ? ($uid . '|' . $aff) : null;
        $globalKey1 = $sid ? ($sid . '|*') : null;
        $globalKey2 = $uid ? ($uid . '|*') : null;
        $funnel = ($funnelKey1 && isset($funnelMap[$funnelKey1])) ? $funnelMap[$funnelKey1]
                : (($funnelKey2 && isset($uuidMap[$funnelKey2])) ? $uuidMap[$funnelKey2]
                : (($globalKey1 && isset($funnelMap[$globalKey1])) ? $funnelMap[$globalKey1]
                : (($globalKey2 && isset($uuidMap[$globalKey2])) ? $uuidMap[$globalKey2] : null)));
        return [
            'id' => $item['id'],
            'domainId' => (int)$item['domain_id'],
            'affId' => $item['aff_id'],
            'subId' => $item['sub_id'],
            'pageUrl' => $item['page_url'],
            'referrer' => $item['referrer'],
            'browser' => $item['browser'],
            'device' => $item['device'],
            'ip' => $item['ip_address'],
            'country' => $item['country'],
            'countryCode' => $item['country_code'],
            'wasShaved' => (bool)$item['was_shaved'],
            'smartSkipped' => (bool)($item['smart_skipped'] ?? 0),
            'shaveSessionId' => $item['shaving_session_id'] ?? null,
            'shaveInfo' => isset($item['shaving_session_id']) && isset($sessionMap[$item['shaving_session_id']])
                ? [
                    'mode' => $sessionMap[$item['shaving_session_id']]['mode'] ?? 'remove',
                    'shaveMode' => $sessionMap[$item['shaving_session_id']]['shave_mode'] ?? 'instant',
                    'replaceAffId' => $sessionMap[$item['shaving_session_id']]['replace_aff_id'] ?? null,
                    'replaceSubId' => $sessionMap[$item['shaving_session_id']]['replace_sub_id'] ?? null,
                    'cbPathFind' => $sessionMap[$item['shaving_session_id']]['cb_path_find'] ?? null,
                    'cbPathReplace' => $sessionMap[$item['shaving_session_id']]['cb_path_replace'] ?? null,
                ] : null,
            'reachedCheckout' => (bool)$item['reached_checkout'],
            'timestamp' => $item['timestamp'],
            'sessionDuration' => $item['session_duration'],
            'maxScrollDepth' => $item['max_scroll_depth'],
            'totalClicks' => $item['total_clicks'],
            'redirectClicks' => (int)($item['redirect_clicks'] ?? 0),
            'buynowClicks' => (int)($item['buynow_clicks'] ?? 0),
            'footerClicks' => (int)($item['footer_clicks'] ?? 0),
            'videoPlays' => (int)($item['video_plays'] ?? 0),
            'videoWatchTime' => isset($item['video_watch_time']) ? (int)$item['video_watch_time'] : null,
            'magicalRevealed' => (int)($item['magical_revealed'] ?? 0),
            'ctaBarClicks' => (int)($item['cta_bar_clicks'] ?? 0),
            'vslClicks' => (int)($item['vsl_clicks'] ?? 0),
            'bounce' => (bool)$item['bounce'],
            'fraudScore' => $item['fraud_score'] !== null ? (int)$item['fraud_score'] : null,
            'fraudRiskLevel' => $item['fraud_risk_level'],
            'fraudFlags' => $item['fraud_flags'],
            'isBot' => (bool)($item['is_bot'] ?? 0),
            'botFlags' => $item['bot_flags'] ?? null,
            'isIframe' => (bool)($item['is_iframe'] ?? 0),
            'hasAdblock' => isset($item['has_adblock']) && $item['has_adblock'] !== null ? (bool)$item['has_adblock'] : null,
            'jsErrorCount' => (int)($item['js_error_count'] ?? 0),
            'jsErrors' => isset($item['js_errors']) && $item['js_errors'] ? json_decode($item['js_errors'], true) : null,
            'pageType' => $item['page_type'] ?? 'landing',
            'sessid2' => $item['sessid2'] ?? null,
            'upsellPages' => $funnel ? $funnel['upsells'] : [],
            'thankyouReached' => $funnel ? (bool)$funnel['thankyou'] : false,
            'matchedOrderId' => ($funnel && $funnel['matchedOrderId']) ? $funnel['matchedOrderId'] : ($item['matched_order_id'] ?? null),
            'toolFraudScore' => calculateToolFraudScore($item),
            'checkoutUrl' => $item['checkout_url'] ?? null,
            'cbParams' => isset($item['cb_params']) && $item['cb_params'] ? json_decode($item['cb_params'], true) : null
        ];
    }, $traffic);

    echo json_encode(['success' => true, 'data' => $formatted, 'total' => $total]);
}

function calculateToolFraudScore($item) {
    $score = 0;
    if (!empty($item['is_bot'])) $score += 40;
    if (!empty($item['is_iframe'])) $score += 20;
    if (isset($item['has_adblock']) && $item['has_adblock'] !== null && $item['has_adblock']) $score += 10;
    if (($item['js_error_count'] ?? 0) > 0) $score += 10;
    if (!empty($item['bounce']) && ($item['total_clicks'] ?? 0) == 0) $score += 10;
    if (($item['session_duration'] ?? 999) > 0 && ($item['session_duration'] ?? 999) < 3) $score += 10;
    return $score;
}

/**
 * Order Data Fraud Analysis — detects suspicious patterns in customer info
 * Returns score 0-100 and list of flags
 */
function analyzeOrderFraud($order) {
    $score = 0;
    $flags = [];

    $name = trim($order['customer_name'] ?? '');
    $email = trim($order['customer_email'] ?? '');
    $address = trim($order['address'] ?? '');
    $city = trim($order['city'] ?? '');
    $state = trim($order['state'] ?? '');

    // --- Name Analysis ---
    if ($name) {
        $nameParts = preg_split('/\s+/', $name);

        // Has middle name → slight reduction (more legitimate)
        $hasMiddleName = count($nameParts) >= 3;
        if ($hasMiddleName) {
            $score -= 5; // Small bonus for having middle name
            $flags[] = ['text' => 'Has middle name', 'type' => 'good'];
        }

        // ALL CAPS name
        if ($name === strtoupper($name) && preg_match('/[A-Z]/', $name)) {
            $score += 20;
            $flags[] = ['text' => 'Name is ALL CAPS', 'type' => 'bad'];
        }

        // Single character first or last name
        foreach ($nameParts as $part) {
            if (strlen($part) === 1 && ctype_alpha($part)) {
                $score += 15;
                $flags[] = ['text' => 'Single letter in name: "' . $part . '"', 'type' => 'bad'];
                break;
            }
        }

        // Name has numbers
        if (preg_match('/\d/', $name)) {
            $score += 20;
            $flags[] = ['text' => 'Name contains numbers', 'type' => 'bad'];
        }

        // Name is suspiciously short (e.g. "A B")
        if (strlen(preg_replace('/\s/', '', $name)) <= 3) {
            $score += 15;
            $flags[] = ['text' => 'Name is very short', 'type' => 'bad'];
        }

        // Repeated characters in name (e.g. "aaa bbb")
        if (preg_match('/(.)\1{3,}/', strtolower($name))) {
            $score += 20;
            $flags[] = ['text' => 'Repeated characters in name', 'type' => 'bad'];
        }

        // Keyboard mash patterns (e.g. "asdf", "qwer")
        $lower = strtolower(preg_replace('/\s/', '', $name));
        $mashPatterns = ['asdf','qwer','zxcv','hjkl','test','fake','none','null','xxx','aaa','bbb'];
        foreach ($mashPatterns as $mp) {
            if (strpos($lower, $mp) !== false) {
                $score += 25;
                $flags[] = ['text' => 'Suspicious pattern in name: "' . $mp . '"', 'type' => 'bad'];
                break;
            }
        }
    }

    // --- Address Analysis ---
    if ($address) {
        // ALL CAPS address
        if ($address === strtoupper($address) && preg_match('/[A-Z]/', $address)) {
            $score += 15;
            $flags[] = ['text' => 'Address is ALL CAPS', 'type' => 'bad'];
        }

        // Very short address
        if (strlen($address) < 8) {
            $score += 15;
            $flags[] = ['text' => 'Address is very short', 'type' => 'bad'];
        }

        // Address looks fake (test, fake, etc.)
        $lAddr = strtolower($address);
        $fakeWords = ['test','fake','none','n/a','na','xxx','123 main','asdf'];
        foreach ($fakeWords as $fw) {
            if (strpos($lAddr, $fw) !== false) {
                $score += 25;
                $flags[] = ['text' => 'Suspicious word in address: "' . $fw . '"', 'type' => 'bad'];
                break;
            }
        }
    }

    // --- City Analysis ---
    if ($city) {
        if ($city === strtoupper($city) && strlen($city) > 2 && preg_match('/[A-Z]/', $city)) {
            $score += 10;
            $flags[] = ['text' => 'City is ALL CAPS', 'type' => 'bad'];
        }
    }

    // --- Email Analysis ---
    if ($email) {
        // Temp email domains
        $tempDomains = ['tempmail','guerrilla','throwaway','yopmail','mailinator','sharklasers','grr.la','guerrillamail','dispostable','10minutemail','trashmail'];
        $emailDomain = strtolower(substr($email, strrpos($email, '@') + 1));
        foreach ($tempDomains as $td) {
            if (strpos($emailDomain, $td) !== false) {
                $score += 20;
                $flags[] = ['text' => 'Temp email domain: ' . $emailDomain, 'type' => 'bad'];
                break;
            }
        }

        // Email with lots of numbers
        $emailLocal = substr($email, 0, strrpos($email, '@'));
        if (preg_match('/\d{6,}/', $emailLocal)) {
            $score += 10;
            $flags[] = ['text' => 'Email has many consecutive numbers', 'type' => 'bad'];
        }
    }

    // --- Mixed Case Pattern (copy-paste indicator) ---
    // Text that has random caps in middle of words suggests copy-paste from formatted source
    if ($name && preg_match('/[a-z][A-Z][a-z]/', $name) && !$hasMiddleName) {
        $score += 10;
        $flags[] = ['text' => 'Irregular capitalization in name (possible copy-paste)', 'type' => 'warn'];
    }
    if ($address && preg_match('/[a-z][A-Z]{2,}[a-z]/', $address)) {
        $score += 10;
        $flags[] = ['text' => 'Irregular capitalization in address (possible copy-paste)', 'type' => 'warn'];
    }

    // Clamp score
    $score = max(0, min(100, $score));

    // Determine level
    if ($score >= 50) $level = 'high';
    elseif ($score >= 25) $level = 'medium';
    else $level = 'low';

    return [
        'score' => $score,
        'level' => $level,
        'flags' => $flags
    ];
}

/**
 * Calculate Address Match Score: compare order shipping address vs IPQS IP geolocation
 * Returns 0-100 score (100 = perfect match, 0 = total mismatch)
 */
function normalizeCountryToCode($country) {
    $country = strtoupper(trim($country));
    if (strlen($country) <= 3) return $country; // Already a code
    $map = [
        'UNITED STATES' => 'US', 'UNITED STATES OF AMERICA' => 'US', 'USA' => 'US',
        'UNITED KINGDOM' => 'GB', 'GREAT BRITAIN' => 'GB', 'ENGLAND' => 'GB',
        'CANADA' => 'CA', 'AUSTRALIA' => 'AU', 'NEW ZEALAND' => 'NZ',
        'GERMANY' => 'DE', 'FRANCE' => 'FR', 'ITALY' => 'IT', 'SPAIN' => 'ES',
        'BRAZIL' => 'BR', 'MEXICO' => 'MX', 'INDIA' => 'IN', 'CHINA' => 'CN',
        'JAPAN' => 'JP', 'SOUTH KOREA' => 'KR', 'RUSSIA' => 'RU',
        'NETHERLANDS' => 'NL', 'BELGIUM' => 'BE', 'SWITZERLAND' => 'CH',
        'SWEDEN' => 'SE', 'NORWAY' => 'NO', 'DENMARK' => 'DK', 'FINLAND' => 'FI',
        'IRELAND' => 'IE', 'PORTUGAL' => 'PT', 'AUSTRIA' => 'AT', 'POLAND' => 'PL',
        'CZECH REPUBLIC' => 'CZ', 'ROMANIA' => 'RO', 'HUNGARY' => 'HU',
        'GREECE' => 'GR', 'TURKEY' => 'TR', 'ISRAEL' => 'IL',
        'SOUTH AFRICA' => 'ZA', 'NIGERIA' => 'NG', 'EGYPT' => 'EG',
        'ARGENTINA' => 'AR', 'COLOMBIA' => 'CO', 'CHILE' => 'CL', 'PERU' => 'PE',
        'PHILIPPINES' => 'PH', 'INDONESIA' => 'ID', 'THAILAND' => 'TH',
        'MALAYSIA' => 'MY', 'SINGAPORE' => 'SG', 'PAKISTAN' => 'PK',
        'SAUDI ARABIA' => 'SA', 'UNITED ARAB EMIRATES' => 'AE', 'UAE' => 'AE',
    ];
    return $map[$country] ?? $country;
}

function haversineDistance($lat1, $lng1, $lat2, $lng2) {
    $R = 6371; // Earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($R * $c);
}

function normalizeUSState($input) {
    $abbr = [
        'al'=>'alabama','ak'=>'alaska','az'=>'arizona','ar'=>'arkansas','ca'=>'california',
        'co'=>'colorado','ct'=>'connecticut','de'=>'delaware','fl'=>'florida','ga'=>'georgia',
        'hi'=>'hawaii','id'=>'idaho','il'=>'illinois','in'=>'indiana','ia'=>'iowa',
        'ks'=>'kansas','ky'=>'kentucky','la'=>'louisiana','me'=>'maine','md'=>'maryland',
        'ma'=>'massachusetts','mi'=>'michigan','mn'=>'minnesota','ms'=>'mississippi','mo'=>'missouri',
        'mt'=>'montana','ne'=>'nebraska','nv'=>'nevada','nh'=>'new hampshire','nj'=>'new jersey',
        'nm'=>'new mexico','ny'=>'new york','nc'=>'north carolina','nd'=>'north dakota','oh'=>'ohio',
        'ok'=>'oklahoma','or'=>'oregon','pa'=>'pennsylvania','ri'=>'rhode island','sc'=>'south carolina',
        'sd'=>'south dakota','tn'=>'tennessee','tx'=>'texas','ut'=>'utah','vt'=>'vermont',
        'va'=>'virginia','wa'=>'washington','wv'=>'west virginia','wi'=>'wisconsin','wy'=>'wyoming',
        'dc'=>'district of columbia'
    ];
    $input = strtolower(trim($input));
    if (isset($abbr[$input])) return $abbr[$input];
    // Check if it's already a full name
    if (in_array($input, $abbr)) return $input;
    return $input;
}

function calculateAddressMatchScore($orderData, $ipqsRaw) {
    if (!$orderData || !$ipqsRaw) return null;

    $score = 0;
    $maxScore = 0;
    $details = [];

    // Country match (40 points) — normalize full names to codes
    $orderCountry = normalizeCountryToCode($orderData['country'] ?? '');
    $ipCountry = strtoupper(trim($ipqsRaw['country_code'] ?? ''));
    if ($orderCountry && $ipCountry) {
        $maxScore += 40;
        if ($orderCountry === $ipCountry) {
            $score += 40;
            $details['country'] = ['match' => true, 'order' => $orderCountry, 'ip' => $ipCountry];
        } else {
            $details['country'] = ['match' => false, 'order' => $orderCountry, 'ip' => $ipCountry];
        }
    }

    // State/Region match (25 points) — with US state abbreviation support
    $orderState = strtolower(trim($orderData['state'] ?? ''));
    $ipRegion = strtolower(trim($ipqsRaw['region'] ?? ''));
    if ($orderState && $ipRegion) {
        $maxScore += 25;
        // Normalize both to full name for comparison
        $orderStateFull = normalizeUSState($orderState);
        $ipRegionFull = normalizeUSState($ipRegion);
        if ($orderStateFull === $ipRegionFull || $orderState === $ipRegion
            || strpos($ipRegion, $orderState) !== false || strpos($orderState, $ipRegion) !== false) {
            $score += 25;
            $details['state'] = ['match' => true, 'order' => $orderData['state'], 'ip' => $ipqsRaw['region']];
        } else {
            similar_text($orderStateFull ?: $orderState, $ipRegionFull ?: $ipRegion, $pct);
            if ($pct >= 70) {
                $score += 15;
                $details['state'] = ['match' => 'partial', 'order' => $orderData['state'], 'ip' => $ipqsRaw['region'], 'similarity' => round($pct)];
            } else {
                $details['state'] = ['match' => false, 'order' => $orderData['state'], 'ip' => $ipqsRaw['region']];
            }
        }
    }

    // City match (25 points)
    $orderCity = strtolower(trim($orderData['city'] ?? ''));
    $ipCity = strtolower(trim($ipqsRaw['city'] ?? ''));
    if ($orderCity && $ipCity) {
        $maxScore += 25;
        if ($orderCity === $ipCity || strpos($ipCity, $orderCity) !== false || strpos($orderCity, $ipCity) !== false) {
            $score += 25;
            $details['city'] = ['match' => true, 'order' => $orderData['city'], 'ip' => $ipqsRaw['city']];
        } else {
            similar_text($orderCity, $ipCity, $pct);
            if ($pct >= 70) {
                $score += 15;
                $details['city'] = ['match' => 'partial', 'order' => $orderData['city'], 'ip' => $ipqsRaw['city'], 'similarity' => round($pct)];
            } else {
                $details['city'] = ['match' => false, 'order' => $orderData['city'], 'ip' => $ipqsRaw['city']];
            }
        }
    }

    // Zip/Postal code match (10 points)
    $orderZip = strtolower(trim($orderData['zip'] ?? ''));
    $ipZip = strtolower(trim($ipqsRaw['zip_code'] ?? $ipqsRaw['zipCode'] ?? $ipqsRaw['postal_code'] ?? $ipqsRaw['zip'] ?? ''));
    if ($orderZip && $ipZip) {
        $maxScore += 10;
        if ($orderZip === $ipZip) {
            $score += 10;
            $details['zip'] = ['match' => true, 'order' => $orderData['zip'], 'ip' => $ipqsRaw['zip_code']];
        } elseif (strpos($orderZip, $ipZip) === 0 || strpos($ipZip, $orderZip) === 0) {
            $score += 5;
            $details['zip'] = ['match' => 'partial', 'order' => $orderData['zip'], 'ip' => $ipqsRaw['zip_code']];
        } else {
            $details['zip'] = ['match' => false, 'order' => $orderData['zip'], 'ip' => $ipqsRaw['zip_code']];
        }
    }

    // Calculate final percentage
    $finalScore = $maxScore > 0 ? round(($score / $maxScore) * 100) : null;
    $level = 'unknown';
    if ($finalScore !== null) {
        if ($finalScore >= 80) $level = 'match';
        elseif ($finalScore >= 50) $level = 'partial';
        else $level = 'mismatch';
    }

    // Calculate distance using IP lat/lng vs order state centroid
    $ipLat = $ipqsRaw['latitude'] ?? null;
    $ipLng = $ipqsRaw['longitude'] ?? null;
    $distanceKm = null;

    if ($ipLat !== null && $ipLng !== null) {
        // US state centroids for distance estimation
        $stateCentroids = [
            'alabama'=>[32.8,-86.8],'alaska'=>[64.2,-152.5],'arizona'=>[34.3,-111.7],
            'arkansas'=>[34.8,-92.2],'california'=>[36.8,-119.4],'colorado'=>[39.1,-105.4],
            'connecticut'=>[41.6,-72.7],'delaware'=>[39.0,-75.5],'florida'=>[27.8,-81.7],
            'georgia'=>[32.7,-83.5],'hawaii'=>[19.9,-155.6],'idaho'=>[44.1,-114.7],
            'illinois'=>[40.6,-89.4],'indiana'=>[40.3,-86.1],'iowa'=>[42.0,-93.2],
            'kansas'=>[38.5,-98.8],'kentucky'=>[37.8,-84.3],'louisiana'=>[30.9,-91.9],
            'maine'=>[45.3,-69.4],'maryland'=>[39.0,-76.6],'massachusetts'=>[42.4,-71.4],
            'michigan'=>[44.3,-85.6],'minnesota'=>[46.4,-94.6],'mississippi'=>[32.7,-89.7],
            'missouri'=>[37.9,-91.8],'montana'=>[46.9,-110.4],'nebraska'=>[41.5,-99.9],
            'nevada'=>[38.8,-116.4],'new hampshire'=>[43.2,-71.6],'new jersey'=>[40.1,-74.5],
            'new mexico'=>[34.5,-105.9],'new york'=>[43.0,-75.5],'north carolina'=>[35.8,-80.0],
            'north dakota'=>[47.5,-100.5],'ohio'=>[40.4,-82.9],'oklahoma'=>[35.6,-97.0],
            'oregon'=>[43.8,-120.6],'pennsylvania'=>[41.2,-77.2],'rhode island'=>[41.6,-71.5],
            'south carolina'=>[34.0,-81.0],'south dakota'=>[43.9,-99.4],'tennessee'=>[35.5,-86.0],
            'texas'=>[31.0,-97.6],'utah'=>[39.3,-111.1],'vermont'=>[44.0,-72.7],
            'virginia'=>[37.8,-79.4],'washington'=>[47.4,-120.7],'west virginia'=>[38.5,-80.5],
            'wisconsin'=>[43.8,-88.8],'wyoming'=>[43.1,-107.6],
            'district of columbia'=>[38.9,-77.0],'dc'=>[38.9,-77.0],
        ];
        $orderStateLower = strtolower(trim($orderData['state'] ?? ''));
        if (isset($stateCentroids[$orderStateLower])) {
            $oCoords = $stateCentroids[$orderStateLower];
            $distanceKm = haversineDistance($oCoords[0], $oCoords[1], $ipLat, $ipLng);
        }
    }

    return [
        'score' => $finalScore,
        'level' => $level,
        'details' => $details,
        'ipLat' => $ipLat,
        'ipLng' => $ipLng,
        'ipTimezone' => $ipqsRaw['timezone'] ?? null,
        'distanceKm' => $distanceKm
    ];
}

/**
 * API endpoint: Get address match score for a traffic entry
 */
function getAddressMatch($pdo) {
    $data = getPostData();
    $trafficId = $data['traffic_id'] ?? null;

    if (empty($trafficId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing traffic_id']);
        return;
    }

    // Get traffic record with IPQS raw data and matched order
    $stmt = $pdo->prepare("SELECT ip_address, ipqs_raw, matched_order_id, domain_id, sessid2, session_uuid, aff_id, timestamp FROM affiliate_traffic WHERE id = ?");
    $stmt->execute([$trafficId]);
    $traffic = $stmt->fetch();

    if (!$traffic) {
        echo json_encode(['success' => false, 'error' => 'Traffic not found']);
        return;
    }

    // Find the matched order - try matched_order_id first, then find via funnel
    $order = null;
    $orderId = $traffic['matched_order_id'];

    if (empty($orderId)) {
        // Try to find order from thankyou page in same session
        $sessid2 = $traffic['sessid2'];
        $uuid = $traffic['session_uuid'];
        if ($sessid2) {
            $oStmt = $pdo->prepare("SELECT matched_order_id FROM affiliate_traffic WHERE sessid2 = ? AND matched_order_id IS NOT NULL LIMIT 1");
            $oStmt->execute([$sessid2]);
            $oRow = $oStmt->fetch();
            if ($oRow) $orderId = $oRow['matched_order_id'];
        }
        if (empty($orderId) && $uuid) {
            $oStmt = $pdo->prepare("SELECT matched_order_id FROM affiliate_traffic WHERE session_uuid = ? AND matched_order_id IS NOT NULL LIMIT 1");
            $oStmt->execute([$uuid]);
            $oRow = $oStmt->fetch();
            if ($oRow) $orderId = $oRow['matched_order_id'];
        }
    }

    if (!empty($orderId)) {
        $oStmt = $pdo->prepare("SELECT city, state, country, zip FROM orders WHERE order_id = ? AND domain_id = ?");
        $oStmt->execute([$orderId, $traffic['domain_id']]);
        $order = $oStmt->fetch();
    }

    // If no matched order, try finding by IP address or affiliate ID
    if (!$order) {
        // Try IP match first (most recent order from same IP)
        $oStmt = $pdo->prepare("SELECT city, state, country, zip FROM orders WHERE ip_address = ? AND domain_id = ? ORDER BY date_created DESC LIMIT 1");
        $oStmt->execute([$traffic['ip_address'], $traffic['domain_id']]);
        $order = $oStmt->fetch();
    }
    if (!$order && !empty($traffic['aff_id'])) {
        // Try affiliate ID match (most recent order from same affiliate, within 1 hour)
        $oStmt = $pdo->prepare("SELECT city, state, country, zip FROM orders WHERE affiliate_id = ? AND domain_id = ? AND ABS(TIMESTAMPDIFF(MINUTE, date_created, ?)) <= 60 ORDER BY date_created DESC LIMIT 1");
        $oStmt->execute([$traffic['aff_id'], $traffic['domain_id'], $traffic['timestamp'] ?? date('Y-m-d H:i:s')]);
        $order = $oStmt->fetch();
    }

    // Get IPQS raw data
    $ipqsRaw = null;
    if (!empty($traffic['ipqs_raw'])) {
        $ipqsRaw = json_decode($traffic['ipqs_raw'], true);
    }
    if (!$ipqsRaw) {
        // Check other records with same IP
        $rawStmt = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL LIMIT 1");
        $rawStmt->execute([$traffic['ip_address']]);
        $rawRow = $rawStmt->fetch();
        if ($rawRow) $ipqsRaw = json_decode($rawRow['ipqs_raw'], true);
    }

    // Auto-run IPQS if no data and we have an order (visitor is a buyer)
    if (!$ipqsRaw && $order && !empty($traffic['ip_address'])) {
        try {
            // Check cache
            $cacheStmt = $pdo->prepare("SELECT fraud_score, fraud_risk_level, fraud_flags, ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL LIMIT 1");
            $cacheStmt->execute([$traffic['ip_address']]);
            $cached = $cacheStmt->fetch();
            if ($cached && $cached['ipqs_raw']) {
                $ipqsRaw = json_decode($cached['ipqs_raw'], true);
                // Copy to this record
                $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = COALESCE(fraud_score, ?), fraud_risk_level = COALESCE(fraud_risk_level, ?), fraud_flags = COALESCE(fraud_flags, ?), ipqs_raw = COALESCE(ipqs_raw, ?) WHERE id = ?");
                $upd->execute([$cached['fraud_score'], $cached['fraud_risk_level'], $cached['fraud_flags'], $cached['ipqs_raw'], $trafficId]);
            } elseif (canCallIPQS($pdo)) {
                require_once __DIR__ . '/ipqs.php';
                $ipqs = new IPQS(IPQS_API_KEYS);
                $result = $ipqs->analyzeIP($traffic['ip_address']);
                if ($result) {
                    $ipqsRaw = $result;
                    $storageData = $ipqs->getStorageData($result);
                    $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ?, ipqs_raw = ? WHERE id = ?");
                    $upd->execute([$storageData['fraud_score'], $storageData['fraud_risk_level'], $storageData['fraud_flags'], json_encode($result), $trafficId]);
                    incrementIPQSCounter($pdo);
                }
            }
        } catch (\Exception $e) {}
    }

    if (!$order && !$ipqsRaw) {
        echo json_encode(['success' => true, 'addressMatch' => null, 'reason' => 'No order or IPQS data']);
        return;
    }
    if (!$order) {
        echo json_encode(['success' => true, 'addressMatch' => null, 'reason' => 'No matched order']);
        return;
    }
    if (!$ipqsRaw) {
        echo json_encode(['success' => true, 'addressMatch' => null, 'reason' => 'No IPQS data']);
        return;
    }

    $result = calculateAddressMatchScore($order, $ipqsRaw);
    echo json_encode(['success' => true, 'addressMatch' => $result]);
}

/**
 * Address match for orders page — uses IP + order_id directly (no traffic_id needed)
 */
function getAddressMatchOrder($pdo) {
    $data = getPostData();
    $ip = $data['ip_address'] ?? '';
    $orderId = $data['order_id'] ?? '';
    $domainId = $data['domain_id'] ?? 0;

    if (empty($ip) || empty($orderId)) {
        echo json_encode(['success' => false, 'error' => 'Missing ip_address or order_id']);
        return;
    }

    // Get order address
    $oStmt = $pdo->prepare("SELECT city, state, country, zip FROM orders WHERE order_id = ? AND domain_id = ?");
    $oStmt->execute([$orderId, $domainId]);
    $order = $oStmt->fetch();

    if (!$order) {
        echo json_encode(['success' => true, 'addressMatch' => null, 'reason' => 'Order not found']);
        return;
    }

    // Get IPQS raw data for this IP
    $ipqsRaw = null;
    $rawStmt = $pdo->prepare("SELECT ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND ipqs_raw IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
    $rawStmt->execute([$ip]);
    $rawRow = $rawStmt->fetch();
    if ($rawRow) $ipqsRaw = json_decode($rawRow['ipqs_raw'], true);

    if (!$ipqsRaw) {
        echo json_encode(['success' => true, 'addressMatch' => null, 'reason' => 'No IPQS data for this IP']);
        return;
    }

    $result = calculateAddressMatchScore($order, $ipqsRaw);
    echo json_encode(['success' => true, 'addressMatch' => $result]);
}

function getTrafficChart($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $period = $data['period'] ?? $_GET['period'] ?? 'today';
    $domainFilter = !empty($domainId) ? " AND domain_id = " . (int)$domainId : "";

    // Build advanced filter conditions (parameterized)
    $extraWhere = '';
    $filterParams = [];
    $affId = $data['aff_id'] ?? '';
    if (!empty($affId)) { $extraWhere .= " AND aff_id = ?"; $filterParams[] = $affId; }
    $landingPage = $data['landing_page'] ?? '';
    if (!empty($landingPage)) { $extraWhere .= " AND page_url LIKE ?"; $filterParams[] = '%' . $landingPage . '%'; }
    $scrollDepth = $data['scroll_depth'] ?? '';
    if ($scrollDepth === 'low') $extraWhere .= " AND (max_scroll_depth IS NULL OR max_scroll_depth < 25)";
    elseif ($scrollDepth === 'medium') $extraWhere .= " AND max_scroll_depth BETWEEN 25 AND 74";
    elseif ($scrollDepth === 'high') $extraWhere .= " AND max_scroll_depth >= 75";
    $checkoutStatus = $data['checkout_status'] ?? '';
    if ($checkoutStatus !== '') { $extraWhere .= " AND reached_checkout = ?"; $filterParams[] = ($checkoutStatus === 'yes') ? 1 : 0; }
    $pageTypeFilter = $data['page_type'] ?? '';
    if (!empty($pageTypeFilter)) { $extraWhere .= " AND page_type = ?"; $filterParams[] = $pageTypeFilter; }

    // Determine grouping and interval based on period
    if (in_array($period, ['today', 'yesterday'])) {
        $timeFilter = getTimeFilter($period);
        $groupBy = "DATE_FORMAT(timestamp, '%H:00')";
        $orderBy = "DATE_FORMAT(timestamp, '%H:00')";
    } else {
        $timeFilter = getTimeFilter($period);
        $groupBy = "DATE(timestamp)";
        $orderBy = "DATE(timestamp)";
    }

    $stmt = $pdo->prepare("
        SELECT
            $groupBy as label,
            COUNT(*) as visits,
            SUM(was_shaved) as shaved
        FROM affiliate_traffic
        WHERE $timeFilter $domainFilter $extraWhere
        GROUP BY label ORDER BY $orderBy ASC
    ");
    $stmt->execute($filterParams);
    $rows = $stmt->fetchAll();

    $labels = [];
    $totalData = [];
    $shavedData = [];

    foreach ($rows as $row) {
        $labels[] = $row['label'];
        $totalData[] = (int)$row['visits'];
        $shavedData[] = (int)($row['shaved'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'totalData' => $totalData,
        'shavedData' => $shavedData
    ]);
}

function matchTrafficOrders($pdo) {
    $matched = 0;

    // Get domain platforms for matching strategy
    $platformMap = [];
    $domStmt = $pdo->query("SELECT id, platform FROM domains");
    foreach ($domStmt->fetchAll() as $d) $platformMap[$d['id']] = $d['platform'];

    // Find all unmatched orders
    $orderStmt = $pdo->query("
        SELECT o.order_id, o.ip_address, o.affiliate_id, o.date_created, o.domain_id
        FROM orders o
        LEFT JOIN affiliate_traffic at2 ON at2.matched_order_id = o.order_id
        WHERE at2.id IS NULL
        ORDER BY o.date_created DESC LIMIT 200
    ");
    $unmatchedOrders = $orderStmt->fetchAll();

    foreach ($unmatchedOrders as $ord) {
        $trafficMatch = null;
        $platform = $platformMap[$ord['domain_id']] ?? 'buygoods';
        $hasIP = !empty($ord['ip_address']) && $ord['ip_address'] !== '-';

        $affId = $ord['affiliate_id'] ?? '';
        $hasAff = !empty($affId) && $affId !== '-';

        // Strategy A: IP-based matching (works best for BuyGoods where checkout IP = landing IP)
        if ($hasIP) {
            // Try IP + same affiliate + domain + within 2 hours (case-insensitive aff match)
            if ($hasAff) {
                $tStmt = $pdo->prepare("
                    SELECT id FROM affiliate_traffic
                    WHERE ip_address = ? AND domain_id = ? AND LOWER(aff_id) = LOWER(?) AND page_type = 'landing'
                    AND matched_order_id IS NULL
                    AND timestamp <= ? AND timestamp >= DATE_SUB(?, INTERVAL 2 HOUR)
                    ORDER BY timestamp DESC LIMIT 1
                ");
                $tStmt->execute([$ord['ip_address'], $ord['domain_id'], $affId, $ord['date_created'], $ord['date_created']]);
                $trafficMatch = $tStmt->fetch();
            }

            // Fallback: IP + domain only (affiliate may differ due to shaving)
            if (!$trafficMatch) {
                $tStmt = $pdo->prepare("
                    SELECT id FROM affiliate_traffic
                    WHERE ip_address = ? AND domain_id = ? AND page_type = 'landing'
                    AND matched_order_id IS NULL
                    AND timestamp <= ? AND timestamp >= DATE_SUB(?, INTERVAL 2 HOUR)
                    ORDER BY timestamp DESC LIMIT 1
                ");
                $tStmt->execute([$ord['ip_address'], $ord['domain_id'], $ord['date_created'], $ord['date_created']]);
                $trafficMatch = $tStmt->fetch();
            }
        }

        // Strategy B: Affiliate + time matching (for ClickBank where checkout IP differs from landing IP)
        if (!$trafficMatch && in_array($platform, ['clickbank', 'digistore24'])) {
            if ($hasAff) {
                $tStmt = $pdo->prepare("
                    SELECT id FROM affiliate_traffic
                    WHERE domain_id = ? AND LOWER(aff_id) = LOWER(?) AND page_type = 'landing'
                    AND matched_order_id IS NULL
                    AND timestamp <= ? AND timestamp >= DATE_SUB(?, INTERVAL 4 HOUR)
                    ORDER BY timestamp DESC LIMIT 1
                ");
                $tStmt->execute([$ord['domain_id'], $affId, $ord['date_created'], $ord['date_created']]);
                $trafficMatch = $tStmt->fetch();
            }
        }

        // Strategy C: For any platform — loose match by affiliate + domain + within 24 hours (last resort)
        if (!$trafficMatch && $hasAff) {
            $tStmt = $pdo->prepare("
                SELECT id FROM affiliate_traffic
                WHERE domain_id = ? AND LOWER(aff_id) = LOWER(?) AND page_type = 'landing'
                AND matched_order_id IS NULL
                AND timestamp <= ? AND timestamp >= DATE_SUB(?, INTERVAL 24 HOUR)
                ORDER BY timestamp DESC LIMIT 1
            ");
            $tStmt->execute([$ord['domain_id'], $affId, $ord['date_created'], $ord['date_created']]);
            $trafficMatch = $tStmt->fetch();
        }

        if ($trafficMatch) {
            $upd = $pdo->prepare("UPDATE affiliate_traffic SET matched_order_id = ? WHERE id = ?");
            $upd->execute([$ord['order_id'], $trafficMatch['id']]);
            $matched++;

            // Auto-run IPQS fraud check for matched orders if not already scored
            autoRunIpqs($pdo, $trafficMatch['id']);
        }
    }

    // Also check thankyou pages (for platforms that redirect to our thankyou)
    $stmt = $pdo->query("
        SELECT id, ip_address, aff_id, domain_id, timestamp
        FROM affiliate_traffic
        WHERE page_type = 'thankyou' AND matched_order_id IS NULL
        ORDER BY timestamp DESC LIMIT 100
    ");
    $thankyouRecords = $stmt->fetchAll();

    foreach ($thankyouRecords as $rec) {
        if (empty($rec['ip_address'])) continue;

        $orderStmt = $pdo->prepare("
            SELECT order_id FROM orders
            WHERE ip_address = ? AND domain_id = ? AND ABS(TIMESTAMPDIFF(MINUTE, date_created, ?)) <= 30
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, date_created, ?)) ASC LIMIT 1
        ");
        $orderStmt->execute([$rec['ip_address'], $rec['domain_id'], $rec['timestamp'], $rec['timestamp']]);
        $order = $orderStmt->fetch();

        if ($order) {
            $updateStmt = $pdo->prepare("UPDATE affiliate_traffic SET matched_order_id = ? WHERE id = ?");
            $updateStmt->execute([$order['order_id'], $rec['id']]);
            $matched++;
        }
    }

    echo json_encode(['success' => true, 'matched' => $matched, 'total_unmatched' => count($unmatchedOrders)]);
}

function getBehaviorDetails($pdo) {
    $data = getPostData();
    $trafficId = $data['traffic_id'] ?? $_GET['traffic_id'] ?? null;

    if (empty($trafficId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing traffic_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM affiliate_traffic WHERE id = ?");
    $stmt->execute([$trafficId]);
    $sessionInfo = $stmt->fetch();

    if (!$sessionInfo) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        return;
    }

    $stmt = $pdo->prepare("SELECT event_type, event_data, timestamp FROM user_behavior_events WHERE traffic_id = ? ORDER BY timestamp ASC");
    $stmt->execute([$trafficId]);
    $events = $stmt->fetchAll();

    foreach ($events as &$event) {
        $event['event_data'] = json_decode($event['event_data'], true);
    }

    // Funnel journey: same sessid2/session_uuid + same domain + within 4 hours of this visit
    $funnelJourney = [];
    $domId = $sessionInfo['domain_id'];
    $visitTime = $sessionInfo['timestamp'];
    if (!empty($sessionInfo['sessid2'])) {
        $funnelStmt = $pdo->prepare("
            SELECT id, page_type, page_url, aff_id, timestamp, max_scroll_depth, total_clicks, session_duration
            FROM affiliate_traffic
            WHERE sessid2 = ? AND domain_id = ?
            AND timestamp >= DATE_SUB(?, INTERVAL 4 HOUR) AND timestamp <= DATE_ADD(?, INTERVAL 4 HOUR)
            ORDER BY timestamp ASC
        ");
        $funnelStmt->execute([$sessionInfo['sessid2'], $domId, $visitTime, $visitTime]);
        $funnelJourney = $funnelStmt->fetchAll();
    }
    if (empty($funnelJourney) && !empty($sessionInfo['session_uuid'])) {
        $funnelStmt = $pdo->prepare("
            SELECT id, page_type, page_url, aff_id, timestamp, max_scroll_depth, total_clicks, session_duration
            FROM affiliate_traffic
            WHERE session_uuid = ? AND domain_id = ?
            AND timestamp >= DATE_SUB(?, INTERVAL 4 HOUR) AND timestamp <= DATE_ADD(?, INTERVAL 4 HOUR)
            ORDER BY timestamp ASC
        ");
        $funnelStmt->execute([$sessionInfo['session_uuid'], $domId, $visitTime, $visitTime]);
        $funnelJourney = $funnelStmt->fetchAll();
    }

    // For shaved visits, fetch before/after snapshot from shave_snapshots
    $shaveSnapshot = null;
    if ($sessionInfo['was_shaved']) {
        // Match by IP + domain_id, closest timestamp
        $snapStmt = $pdo->prepare("
            SELECT * FROM shave_snapshots
            WHERE domain_id = ? AND ip_address = ?
            AND ABS(TIMESTAMPDIFF(SECOND, before_at, ?)) < 60
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, before_at, ?)) ASC LIMIT 1
        ");
        $snapStmt->execute([
            $sessionInfo['domain_id'],
            $sessionInfo['ip_address'],
            $sessionInfo['timestamp'],
            $sessionInfo['timestamp']
        ]);
        $shaveSnapshot = $snapStmt->fetch();
    }

    // Also include click data for each funnel step
    if (!empty($funnelJourney)) {
        foreach ($funnelJourney as &$fj) {
            $fjStmt = $pdo->prepare("SELECT redirect_clicks, buynow_clicks, footer_clicks, video_plays, cta_bar_clicks, vsl_clicks, max_scroll_depth, session_duration, reached_checkout FROM affiliate_traffic WHERE id = ?");
            $fjStmt->execute([$fj['id']]);
            $fjExtra = $fjStmt->fetch();
            if ($fjExtra) {
                $fj['redirect_clicks'] = (int)($fjExtra['redirect_clicks'] ?? 0);
                $fj['buynow_clicks'] = (int)($fjExtra['buynow_clicks'] ?? 0);
                $fj['footer_clicks'] = (int)($fjExtra['footer_clicks'] ?? 0);
                $fj['video_plays'] = (int)($fjExtra['video_plays'] ?? 0);
                $fj['cta_bar_clicks'] = (int)($fjExtra['cta_bar_clicks'] ?? 0);
                $fj['vsl_clicks'] = (int)($fjExtra['vsl_clicks'] ?? 0);
                $fj['reached_checkout'] = (int)($fjExtra['reached_checkout'] ?? 0);
            }
        }
    }

    // Shaving session details
    $shaveSessionInfo = null;
    if (!empty($sessionInfo['shaving_session_id'])) {
        $ssStmt = $pdo->prepare("SELECT mode, shave_mode, replace_aff_id, replace_sub_id, cb_path_find, cb_path_replace FROM shaving_sessions WHERE id = ?");
        $ssStmt->execute([$sessionInfo['shaving_session_id']]);
        $shaveSessionInfo = $ssStmt->fetch(PDO::FETCH_ASSOC);
        if (!$shaveSessionInfo) {
            $shStmt = $pdo->prepare("SELECT mode, shave_mode FROM shaving_history WHERE session_id = ?");
            $shStmt->execute([$sessionInfo['shaving_session_id']]);
            $shaveSessionInfo = $shStmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'session' => $sessionInfo,
        'events' => $events,
        'funnelJourney' => $funnelJourney,
        'shaveSnapshot' => $shaveSnapshot,
        'shaveSessionInfo' => $shaveSessionInfo
    ]);
}

function getHeatmapData($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $period = $data['period'] ?? $_GET['period'] ?? 'today';
    $pageUrl = $data['page_url'] ?? $_GET['page_url'] ?? '';

    if (empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'domain_id required']);
        return;
    }

    $timeFilter = getTimeFilter($period);
    $domainFilter = " AND domain_id = " . (int)$domainId;
    $urlFilter = '';
    $urlParams = [];
    if (!empty($pageUrl)) {
        $urlFilter = " AND page_url LIKE ?";
        $urlParams[] = '%' . parse_url($pageUrl, PHP_URL_PATH) . '%';
    }

    // Aggregate click positions from all matching traffic
    $stmt = $pdo->prepare("
        SELECT click_positions, max_scroll_depth
        FROM affiliate_traffic
        WHERE $timeFilter $domainFilter $urlFilter
        AND click_positions IS NOT NULL
    ");
    $stmt->execute($urlParams);
    $rows = $stmt->fetchAll();

    $allClicks = [];
    $scrollDepths = [];
    foreach ($rows as $row) {
        $positions = json_decode($row['click_positions'], true);
        if (is_array($positions)) {
            foreach ($positions as $pos) {
                $allClicks[] = $pos;
            }
        }
        if ($row['max_scroll_depth'] > 0) {
            $scrollDepths[] = (int)$row['max_scroll_depth'];
        }
    }

    // Also get scroll depth distribution from ALL traffic (not just those with clicks)
    $scrollStmt = $pdo->prepare("
        SELECT max_scroll_depth, COUNT(*) as cnt
        FROM affiliate_traffic
        WHERE $timeFilter $domainFilter $urlFilter
        AND max_scroll_depth > 0
        GROUP BY max_scroll_depth ORDER BY max_scroll_depth
    ");
    $scrollStmt->execute($urlParams);
    $scrollDist = $scrollStmt->fetchAll();

    // Total visitors for scroll percentage
    $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM affiliate_traffic WHERE $timeFilter $domainFilter $urlFilter");
    $totalStmt->execute($urlParams);
    $totalVisitors = (int)$totalStmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'clicks' => $allClicks,
        'scrollDistribution' => $scrollDist,
        'totalVisitors' => $totalVisitors,
        'totalClickRecords' => count($rows)
    ]);
}

function getHeatmapPages($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';

    if (empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'domain_id required']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT
            SUBSTRING_INDEX(page_url, '?', 1) as clean_url,
            COUNT(*) as visits,
            SUM(CASE WHEN click_positions IS NOT NULL THEN 1 ELSE 0 END) as with_clicks
        FROM affiliate_traffic
        WHERE domain_id = ? AND page_type = 'landing' AND page_url IS NOT NULL AND page_url != ''
        GROUP BY SUBSTRING_INDEX(page_url, '?', 1)
        ORDER BY visits DESC
        LIMIT 30
    ");
    $stmt->execute([(int)$domainId]);
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'pages' => $pages]);
}

function getVisitorSession($pdo) {
    $data = getPostData();
    $trafficId = $data['traffic_id'] ?? $_GET['traffic_id'] ?? '';

    if (empty($trafficId)) {
        echo json_encode(['success' => false, 'error' => 'traffic_id required']);
        return;
    }

    // Get the traffic record
    $stmt = $pdo->prepare("SELECT * FROM affiliate_traffic WHERE id = ?");
    $stmt->execute([(int)$trafficId]);
    $traffic = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$traffic) {
        echo json_encode(['success' => false, 'error' => 'Traffic record not found']);
        return;
    }

    // Get behavior events for this traffic record
    $evtStmt = $pdo->prepare("SELECT event_type, event_data, timestamp FROM user_behavior_events WHERE traffic_id = ? ORDER BY timestamp ASC");
    $evtStmt->execute([(int)$trafficId]);
    $events = $evtStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($events as &$evt) {
        $evt['event_data'] = json_decode($evt['event_data'], true);
    }

    // Parse click positions
    $clicks = [];
    if (!empty($traffic['click_positions'])) {
        $clicks = json_decode($traffic['click_positions'], true) ?: [];
    }

    // Get shave snapshot if shaved — match by IP + domain_id + close timestamp
    $shaveSnapshot = null;
    if ($traffic['was_shaved']) {
        $snapStmt = $pdo->prepare("
            SELECT before_url, after_url, sub_id, replace_sub_id, ip_address AS snap_ip
            FROM shave_snapshots
            WHERE domain_id = ? AND ip_address = ?
            AND ABS(TIMESTAMPDIFF(SECOND, before_at, ?)) < 120
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, before_at, ?)) ASC LIMIT 1
        ");
        $snapStmt->execute([
            $traffic['domain_id'],
            $traffic['ip_address'],
            $traffic['timestamp'],
            $traffic['timestamp']
        ]);
        $shaveSnapshot = $snapStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    echo json_encode([
        'success' => true,
        'traffic' => [
            'id' => $traffic['id'],
            'aff_id' => $traffic['aff_id'],
            'page_url' => $traffic['page_url'],
            'ip_address' => $traffic['ip_address'],
            'country' => $traffic['country'],
            'device' => $traffic['device'],
            'browser' => $traffic['browser'],
            'was_shaved' => (bool)$traffic['was_shaved'],
            'session_duration' => $traffic['session_duration'],
            'max_scroll_depth' => $traffic['max_scroll_depth'],
            'total_clicks' => (int)($traffic['total_clicks'] ?? 0),
            'redirect_clicks' => (int)($traffic['redirect_clicks'] ?? 0),
            'buynow_clicks' => (int)($traffic['buynow_clicks'] ?? 0),
            'footer_clicks' => (int)($traffic['footer_clicks'] ?? 0),
            'video_plays' => (int)($traffic['video_plays'] ?? 0),
            'cta_bar_clicks' => (int)($traffic['cta_bar_clicks'] ?? 0),
            'vsl_clicks' => (int)($traffic['vsl_clicks'] ?? 0),
            'timestamp' => $traffic['timestamp'],
            'redirect_time_ms' => $traffic['redirect_time_ms'],
            'domain_id' => $traffic['domain_id'],
            'viewport_width' => $traffic['viewport_width'] ?? null,
            'viewport_height' => $traffic['viewport_height'] ?? null,
        ],
        'clicks' => $clicks,
        'events' => $events,
        'shaveSnapshot' => $shaveSnapshot,
    ]);
}

function getDashboardStats($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? '';
    $domainFilter = !empty($domainId) ? " AND domain_id = " . (int)$domainId : "";

    // Active sessions count
    $sql = "SELECT COUNT(*) as count FROM shaving_sessions WHERE active = 1";
    if (!empty($domainId)) $sql .= " AND domain_id = " . (int)$domainId;
    $activeSessions = $pdo->query($sql)->fetch()['count'];

    // Today's visits
    $todayFilter = getTimeFilter('today');
    $stmt = $pdo->query("
        SELECT COUNT(*) as total, SUM(was_shaved) as shaved
        FROM affiliate_traffic WHERE $todayFilter $domainFilter
    ");
    $todayStats = $stmt->fetch();

    // Registered domains count
    $totalDomains = $pdo->query("SELECT COUNT(*) as count FROM domains WHERE status = 'active'")->fetch()['count'];

    echo json_encode([
        'success' => true,
        'activeSessions' => (int)$activeSessions,
        'visitsToday' => (int)$todayStats['total'],
        'shavedToday' => (int)($todayStats['shaved'] ?? 0),
        'totalDomains' => (int)$totalDomains
    ]);
}

// ================================================================
// HELPER FUNCTIONS
// ================================================================

function generateDomainKey($url) {
    $url = preg_replace('#^https?://#', '', $url);
    $url = rtrim($url, '/');
    $key = preg_replace('/[^a-zA-Z0-9]+/', '-', $url);
    $key = strtolower(trim($key, '-'));
    if (strlen($key) > 60) $key = substr($key, 0, 60);
    return $key;
}

function getTimeFilter($period) {
    $tz = new DateTimeZone('Asia/Karachi');
    $now = new DateTime('now', $tz);

    switch ($period) {
        case 'today':
            $start = clone $now; $start->setTime(0, 0, 0);
            $end = clone $now; $end->setTime(23, 59, 59);
            break;
        case 'yesterday':
            $start = clone $now; $start->modify('-1 day')->setTime(0, 0, 0);
            $end = clone $now; $end->modify('-1 day')->setTime(23, 59, 59);
            break;
        case 'this_week':
        case 'thisweek':
            $dayOfWeek = $now->format('N');
            $start = clone $now; $start->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
            $end = clone $start; $end->modify('+6 days')->setTime(23, 59, 59);
            break;
        case 'last_week':
        case 'lastweek':
            $dayOfWeek = $now->format('N');
            $start = clone $now; $start->modify('-' . ($dayOfWeek + 6) . ' days')->setTime(0, 0, 0);
            $end = clone $start; $end->modify('+6 days')->setTime(23, 59, 59);
            break;
        case 'this_month':
        case 'thismonth':
            $start = clone $now; $start->modify('first day of this month')->setTime(0, 0, 0);
            $end = clone $now; $end->modify('last day of this month')->setTime(23, 59, 59);
            break;
        case 'all':
            return "1=1";
        default:
            $start = clone $now; $start->setTime(0, 0, 0);
            $end = clone $now; $end->setTime(23, 59, 59);
    }

    return "timestamp >= '" . $start->format('Y-m-d H:i:s') . "' AND timestamp <= '" . $end->format('Y-m-d H:i:s') . "'";
}

function getPreviousPeriodFilter($period) {
    $tz = new DateTimeZone('Asia/Karachi');
    $now = new DateTime('now', $tz);

    switch ($period) {
        case 'today':
            $start = clone $now; $start->modify('-1 day')->setTime(0, 0, 0);
            $end = clone $now; $end->modify('-1 day')->setTime(23, 59, 59);
            break;
        case 'yesterday':
            $start = clone $now; $start->modify('-2 days')->setTime(0, 0, 0);
            $end = clone $now; $end->modify('-2 days')->setTime(23, 59, 59);
            break;
        case 'this_week':
        case 'thisweek':
            $dayOfWeek = $now->format('N');
            $start = clone $now; $start->modify('-' . ($dayOfWeek + 6) . ' days')->setTime(0, 0, 0);
            $end = clone $start; $end->modify('+6 days')->setTime(23, 59, 59);
            break;
        case 'last_week':
        case 'lastweek':
            $dayOfWeek = $now->format('N');
            $start = clone $now; $start->modify('-' . ($dayOfWeek + 13) . ' days')->setTime(0, 0, 0);
            $end = clone $start; $end->modify('+6 days')->setTime(23, 59, 59);
            break;
        case 'this_month':
        case 'thismonth':
            $start = clone $now; $start->modify('first day of last month')->setTime(0, 0, 0);
            $end = clone $now; $end->modify('last day of last month')->setTime(23, 59, 59);
            break;
        case 'all':
            return null;
        default:
            $start = clone $now; $start->modify('-1 day')->setTime(0, 0, 0);
            $end = clone $now; $end->modify('-1 day')->setTime(23, 59, 59);
    }

    return "timestamp >= '" . $start->format('Y-m-d H:i:s') . "' AND timestamp <= '" . $end->format('Y-m-d H:i:s') . "'";
}

function canCallIPQS($pdo) {
    $today = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    $stmt = $pdo->prepare("SELECT calls_made FROM ipqs_usage WHERE date = ?");
    $stmt->execute([$today]);
    $row = $stmt->fetch();
    if (!$row) return true;
    return (int)$row['calls_made'] < IPQS_DAILY_LIMIT;
}

function incrementIPQSCounter($pdo) {
    $today = (new DateTime('now', new DateTimeZone('Asia/Karachi')))->format('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO ipqs_usage (date, calls_made) VALUES (?, 1) ON DUPLICATE KEY UPDATE calls_made = calls_made + 1");
    $stmt->execute([$today]);
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return trim($_SERVER['HTTP_CLIENT_IP']);
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return trim($_SERVER['HTTP_X_REAL_IP']);
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function parseBrowserInfo($userAgent) {
    $browser = 'Unknown';
    $device = 'Desktop';

    if (preg_match('/Edg/i', $userAgent)) $browser = 'Edge';
    elseif (preg_match('/Firefox/i', $userAgent)) $browser = 'Firefox';
    elseif (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edg/i', $userAgent)) $browser = 'Chrome';
    elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) $browser = 'Safari';
    elseif (preg_match('/Opera|OPR/i', $userAgent)) $browser = 'Opera';
    elseif (preg_match('/MSIE|Trident/i', $userAgent)) $browser = 'IE';

    if (preg_match('/Mobile|Android|iPhone|iPod|webOS|BlackBerry|IEMobile/i', $userAgent)) {
        $device = preg_match('/iPad|Tablet/i', $userAgent) ? 'Tablet' : 'Mobile';
    }

    return ['browser' => $browser, 'device' => $device];
}

function getGeoInfo($ip) {
    if ($ip === '127.0.0.1' || $ip === '::1' || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return ['country' => 'Local', 'countryCode' => 'LO'];
    }

    $context = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true]]);
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country,countryCode", false, $context);

    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['country'])) {
            return ['country' => $data['country'], 'countryCode' => $data['countryCode'] ?? 'XX'];
        }
    }

    return ['country' => 'Unknown', 'countryCode' => 'XX'];
}

// ================================================================
// ORDERS & CUSTOMERS
// ================================================================

function parseCsvLine($line) {
    // Proper CSV parsing that handles quoted fields with commas inside
    $fields = [];
    $field = '';
    $inQuotes = false;
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($inQuotes) {
            if ($ch === '"') {
                if ($i + 1 < $len && $line[$i + 1] === '"') {
                    $field .= '"';
                    $i++;
                } else {
                    $inQuotes = false;
                }
            } else {
                $field .= $ch;
            }
        } else {
            if ($ch === '"') {
                $inQuotes = true;
            } elseif ($ch === ',') {
                $fields[] = $field;
                $field = '';
            } else {
                $field .= $ch;
            }
        }
    }
    $fields[] = $field;
    return $fields;
}

function cleanAmount($val) {
    if (empty($val)) return 0;
    $val = str_replace(['$', ',', ' '], '', trim($val));
    return is_numeric($val) ? (float)$val : 0;
}

function uploadOrders($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $csvData = $data['csv_data'] ?? '';

    if (empty($domainId) || empty($csvData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing domain_id or csv_data']);
        return;
    }

    $lines = preg_split('/\r?\n/', trim($csvData));
    if (count($lines) < 2) {
        echo json_encode(['error' => 'CSV must have header row and at least one data row']);
        return;
    }

    // Parse header to build column map
    $headerFields = parseCsvLine($lines[0]);
    $colMap = [];
    foreach ($headerFields as $idx => $name) {
        $colMap[trim($name)] = $idx;
    }

    // Map CSV column names to our DB columns
    $mapping = [
        'Order ID' => 'order_id',
        'Date Created' => 'date_created',
        'Status' => 'status',
        'Customer Name' => 'customer_name',
        'Customer Email Address' => 'customer_email',
        'Customer Phone' => 'customer_phone',
        'Address' => 'address',
        'City' => 'city',
        'State' => 'state',
        'Country' => 'country',
        'Zip' => 'zip',
        'Affiliate ID' => 'affiliate_id',
        'Affiliate Name' => 'affiliate_name',
        'Affiliate Commission Amount' => 'affiliate_commission',
        'SubID' => 'sub_id',
        'SubID2' => 'sub_id2',
        'SubID3' => 'sub_id3',
        'SubID4' => 'sub_id4',
        'SubID5' => 'sub_id5',
        'Product Names' => 'product_names',
        'Product Codenames' => 'product_codenames',
        'SKU' => 'sku',
        'Payment Method' => 'payment_method',
        'Total collected (Transaction Amount)' => 'total_amount',
        'Vendor Net' => 'vendor_net',
        'Taxes' => 'taxes',
        'Shipping Cost (Fulfillment)' => 'shipping_cost',
        'Payment Processing Fees' => 'processing_fees',
        'Production Cost (Fulfillment)' => 'production_cost',
        'Handling Cost (Fulfillment)' => 'handling_cost',
        'IP Address' => 'ip_address',
        'Referrer URL' => 'referrer_url',
        'Funnel Codename' => 'funnel_codename',
        'Flag Upsell' => 'flag_upsell',
        'Is Free' => 'is_free',
        'Is Test' => 'is_test',
        'Was Canceled' => 'was_canceled',
        'Date Canceled' => 'date_canceled',
        'Cancel Reason' => 'cancel_reason',
        'User Comments' => 'user_comments',
        'User ID' => 'user_id',
        'Store ID' => 'store_id',
        'Order Details' => 'order_details',
        'External Order ID' => 'external_order_id',
        'External Order ID2' => 'external_order_id2',
    ];

    $moneyFields = ['affiliate_commission', 'total_amount', 'vendor_net', 'taxes', 'shipping_cost', 'processing_fees', 'production_cost', 'handling_cost'];
    $boolFields = ['flag_upsell', 'is_free', 'is_test', 'was_canceled'];

    // Build INSERT ... ON DUPLICATE KEY UPDATE statement
    // NOTE: Only CSV-mapped columns are in $dbCols. Fulfillment tracking columns
    // (fulfillment_status, delay_reason, expected_delivery, delivered_date,
    //  compensation_*, internal_notes, delay_mail_sent) are NOT touched by re-uploads.
    $dbCols = array_values($mapping);
    $colList = 'domain_id, ' . implode(', ', $dbCols);
    $placeholders = '?, ' . implode(', ', array_fill(0, count($dbCols), '?'));
    $updateParts = [];
    foreach ($dbCols as $col) {
        if ($col !== 'order_id') {
            $updateParts[] = "$col = VALUES($col)";
        }
    }
    $updateClause = implode(', ', $updateParts);

    $sql = "INSERT INTO orders ($colList) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateClause";
    $stmt = $pdo->prepare($sql);

    $inserted = 0;
    $skipped = 0;

    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;

        $fields = parseCsvLine($line);
        $row = [];

        // Get order_id first to check if row is valid
        $orderIdIdx = $colMap['Order ID'] ?? null;
        $orderId = ($orderIdIdx !== null && isset($fields[$orderIdIdx])) ? trim($fields[$orderIdIdx]) : '';
        if (empty($orderId)) {
            $skipped++;
            continue;
        }

        $params = [$domainId];
        foreach ($mapping as $csvCol => $dbCol) {
            $idx = $colMap[$csvCol] ?? null;
            $val = ($idx !== null && isset($fields[$idx])) ? trim($fields[$idx]) : '';

            if (in_array($dbCol, $moneyFields)) {
                $params[] = cleanAmount($val);
            } elseif (in_array($dbCol, $boolFields)) {
                $params[] = ($val === '1' || strtolower($val) === 'true' || strtolower($val) === 'yes') ? 1 : 0;
            } elseif ($dbCol === 'date_created' || $dbCol === 'date_canceled') {
                $params[] = !empty($val) ? $val : null;
            } else {
                $params[] = $val !== '' ? $val : null;
            }
        }

        try {
            $stmt->execute($params);
            $inserted++;
        } catch (PDOException $e) {
            $skipped++;
        }
    }

    echo json_encode(['success' => true, 'inserted' => $inserted, 'skipped' => $skipped]);
}

function getOrders($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $limit = intval($data['limit'] ?? 50);
    $offset = intval($data['offset'] ?? 0);
    $search = $data['search'] ?? '';
    $affId = $data['aff_id'] ?? '';
    $status = $data['status'] ?? '';

    $where = ['1=1'];
    $params = [];

    if (!empty($domainId)) {
        $where[] = 'domain_id = ?';
        $params[] = $domainId;
    }
    if (!empty($search)) {
        $where[] = '(customer_name LIKE ? OR customer_email LIKE ? OR order_id LIKE ? OR customer_phone LIKE ?)';
        $s = '%' . $search . '%';
        $params = array_merge($params, [$s, $s, $s, $s]);
    }
    if (!empty($affId)) {
        $where[] = 'affiliate_id = ?';
        $params[] = $affId;
    }
    if (!empty($status)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    // Order ID filter (exact or partial match)
    $orderIdFilter = $data['order_id_filter'] ?? '';
    if (!empty($orderIdFilter)) {
        $where[] = 'order_id LIKE ?';
        $params[] = '%' . $orderIdFilter . '%';
    }
    // Bottles filter (matches product_names pattern)
    $bottlesFilter = $data['bottles'] ?? '';
    if (!empty($bottlesFilter)) {
        $bottles = intval($bottlesFilter);
        if ($bottles > 0) {
            // Match "X Bottle" or "X+Y" patterns where total = bottles
            $where[] = 'product_names LIKE ?';
            $params[] = '%' . $bottles . ' Bottle%';
        }
    }

    $whereClause = implode(' AND ', $where);

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Data
    $dataStmt = $pdo->prepare("SELECT * FROM orders WHERE $whereClause ORDER BY date_created DESC LIMIT ? OFFSET ?");
    $allParams = array_merge($params, [$limit, $offset]);
    $dataStmt->execute($allParams);
    $rows = $dataStmt->fetchAll();

    // Batch fraud score + tool score lookup from affiliate_traffic by IP
    $ips = [];
    foreach ($rows as $r) {
        if (!empty($r['ip_address'])) $ips[$r['ip_address']] = true;
    }
    $fraudMap = [];
    if (!empty($ips)) {
        $ph = implode(',', array_fill(0, count($ips), '?'));
        // Get IPQS scores
        $fStmt = $pdo->prepare("SELECT ip_address, fraud_score, fraud_risk_level, fraud_flags FROM affiliate_traffic WHERE ip_address IN ($ph) AND fraud_score IS NOT NULL GROUP BY ip_address ORDER BY MAX(timestamp) DESC");
        $fStmt->execute(array_keys($ips));
        foreach ($fStmt->fetchAll() as $fr) {
            if (!isset($fraudMap[$fr['ip_address']])) {
                $fraudMap[$fr['ip_address']] = ['fraud_score' => (int)$fr['fraud_score'], 'fraud_risk_level' => $fr['fraud_risk_level'], 'fraud_flags' => $fr['fraud_flags'] ?? '', 'tool_fraud_score' => null];
            }
        }
        // Get tool fraud scores from landing records
        $tStmt = $pdo->prepare("SELECT ip_address, is_bot, is_iframe, has_adblock, js_error_count, bounce, total_clicks, session_duration FROM affiliate_traffic WHERE ip_address IN ($ph) AND page_type = 'landing' ORDER BY timestamp DESC");
        $tStmt->execute(array_keys($ips));
        foreach ($tStmt->fetchAll() as $tr) {
            $ip = $tr['ip_address'];
            if (!isset($fraudMap[$ip])) {
                $fraudMap[$ip] = ['fraud_score' => null, 'fraud_risk_level' => null, 'fraud_flags' => '', 'tool_fraud_score' => null];
            }
            if ($fraudMap[$ip]['tool_fraud_score'] === null) {
                $fraudMap[$ip]['tool_fraud_score'] = calculateToolFraudScore($tr);
            }
        }
    }
    // Batch campaign status lookup
    $campaignMap = [];
    if (!empty($domainId) && !empty($rows)) {
        $orderIds = [];
        foreach ($rows as $r) { if (!empty($r['order_id'])) $orderIds[] = $r['order_id']; }
        if (!empty($orderIds)) {
            $cPh = implode(',', array_fill(0, count($orderIds), '?'));
            try {
                $cStmt = $pdo->prepare("SELECT order_id, status, sends_count, max_sends, next_send_at, last_sent_at, reorder_order_id, created_at FROM reorder_campaigns WHERE domain_id = ? AND order_id IN ($cPh) ORDER BY created_at DESC");
                $cStmt->execute(array_merge([$domainId], $orderIds));
                foreach ($cStmt->fetchAll() as $camp) {
                    $oid = $camp['order_id'];
                    if (!isset($campaignMap[$oid])) {
                        $campaignMap[$oid] = $camp;
                    }
                }
            } catch (Exception $e) {
                // Table may not exist yet — ignore
            }
        }
    }

    foreach ($rows as &$r) {
        $ip = $r['ip_address'] ?? '';
        $r['fraud_score'] = isset($fraudMap[$ip]) ? $fraudMap[$ip]['fraud_score'] : null;
        $r['fraud_risk_level'] = isset($fraudMap[$ip]) ? $fraudMap[$ip]['fraud_risk_level'] : null;
        $r['fraud_flags'] = isset($fraudMap[$ip]) ? ($fraudMap[$ip]['fraud_flags'] ?? '') : '';
        $r['tool_fraud_score'] = isset($fraudMap[$ip]) ? $fraudMap[$ip]['tool_fraud_score'] : null;
        $r['order_fraud'] = analyzeOrderFraud($r);
        $r['campaign'] = $campaignMap[$r['order_id']] ?? null;
    }

    echo json_encode(['success' => true, 'data' => $rows, 'total' => intval($total)]);
}

function getOrderStats($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;

    $where = '1=1';
    $params = [];
    if (!empty($domainId)) {
        $where = 'domain_id = ?';
        $params[] = $domainId;
    }

    $stmt = $pdo->prepare("SELECT
        SUM(CASE WHEN status NOT IN ('Refunded','Chargeback','RFND','CGBK') AND total_amount >= 0 THEN 1 ELSE 0 END) as active_orders,
        COALESCE(SUM(CASE WHEN status NOT IN ('Refunded','Chargeback','RFND','CGBK') AND total_amount >= 0 THEN total_amount ELSE 0 END), 0) as active_revenue,
        COALESCE(SUM(CASE WHEN status NOT IN ('Refunded','Chargeback','RFND','CGBK') AND total_amount >= 0 THEN vendor_net ELSE 0 END), 0) as active_vendor_net,
        SUM(CASE WHEN was_canceled = 1 THEN 1 ELSE 0 END) as canceled_orders,
        SUM(CASE WHEN status IN ('Refunded','Chargeback','RFND','CGBK') OR total_amount < 0 THEN 1 ELSE 0 END) as refunded_orders,
        COALESCE(SUM(CASE WHEN status IN ('Refunded','Chargeback','RFND','CGBK') OR total_amount < 0 THEN ABS(total_amount) ELSE 0 END), 0) as refund_total,
        COUNT(DISTINCT customer_email) as total_customers
    FROM orders WHERE $where");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    $activeOrders = intval($stats['active_orders']);
    $activeRevenue = floatval($stats['active_revenue']);
    $avgOrder = $activeOrders > 0 ? round($activeRevenue / $activeOrders, 2) : 0;

    echo json_encode([
        'success' => true,
        'totalOrders' => $activeOrders,
        'totalRevenue' => round($activeRevenue, 2),
        'avgOrderValue' => $avgOrder,
        'vendorNet' => round(floatval($stats['active_vendor_net']), 2),
        'canceledOrders' => intval($stats['canceled_orders']),
        'refundedOrders' => intval($stats['refunded_orders']),
        'refundTotal' => round(floatval($stats['refund_total']), 2),
        'totalCustomers' => intval($stats['total_customers'])
    ]);
}

function getCustomers($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $limit = intval($data['limit'] ?? 50);
    $offset = intval($data['offset'] ?? 0);
    $search = $data['search'] ?? '';

    $where = ['1=1'];
    $params = [];

    if (!empty($domainId)) {
        $where[] = 'domain_id = ?';
        $params[] = $domainId;
    }
    if (!empty($search)) {
        $where[] = '(customer_name LIKE ? OR customer_email LIKE ? OR customer_phone LIKE ? OR city LIKE ?)';
        $s = '%' . $search . '%';
        $params = array_merge($params, [$s, $s, $s, $s]);
    }

    $whereClause = implode(' AND ', $where);

    // Count distinct customers
    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT customer_email) FROM orders WHERE $whereClause AND customer_email IS NOT NULL AND customer_email != ''");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Aggregated customer data
    $dataStmt = $pdo->prepare("SELECT
        customer_email,
        MAX(customer_name) as customer_name,
        MAX(customer_phone) as customer_phone,
        MAX(city) as city,
        MAX(state) as state,
        MAX(country) as country,
        COUNT(*) as total_orders,
        COALESCE(SUM(total_amount), 0) as total_spent,
        MIN(date_created) as first_order,
        MAX(date_created) as last_order,
        SUM(CASE WHEN was_canceled = 1 THEN 1 ELSE 0 END) as canceled_orders,
        GROUP_CONCAT(DISTINCT affiliate_id) as affiliate_ids
    FROM orders
    WHERE $whereClause AND customer_email IS NOT NULL AND customer_email != ''
    GROUP BY customer_email
    ORDER BY MAX(date_created) DESC
    LIMIT ? OFFSET ?");
    $allParams = array_merge($params, [$limit, $offset]);
    $dataStmt->execute($allParams);
    $rows = $dataStmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows, 'total' => intval($total)]);
}

function getCustomerDetails($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $email = $data['customer_email'] ?? '';

    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing customer_email']);
        return;
    }

    $where = 'customer_email = ?';
    $params = [$email];

    if (!empty($domainId)) {
        $where .= ' AND domain_id = ?';
        $params[] = $domainId;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE $where ORDER BY date_created DESC");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Aggregate stats
    $totalSpent = 0;
    $customerInfo = [];
    foreach ($orders as $o) {
        $totalSpent += floatval($o['total_amount']);
        if (empty($customerInfo)) {
            $customerInfo = [
                'name' => $o['customer_name'],
                'email' => $o['customer_email'],
                'phone' => $o['customer_phone'],
                'address' => $o['address'],
                'city' => $o['city'],
                'state' => $o['state'],
                'country' => $o['country'],
                'zip' => $o['zip'],
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'customer' => $customerInfo,
        'orders' => $orders,
        'totalSpent' => round($totalSpent, 2),
        'totalOrders' => count($orders)
    ]);
}

function getOrderDetail($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId = $data['order_id'] ?? '';

    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id']);
        return;
    }

    $where = 'order_id = ?';
    $params = [$orderId];

    if (!empty($domainId)) {
        $where .= ' AND domain_id = ?';
        $params[] = $domainId;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE $where LIMIT 1");
    $stmt->execute($params);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    // Look up cached IPQS fraud score from affiliate_traffic by IP
    $fraudData = null;
    if (!empty($order['ip_address'])) {
        $fraudStmt = $pdo->prepare("SELECT fraud_score, fraud_risk_level, fraud_flags FROM affiliate_traffic WHERE ip_address = ? AND fraud_score IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $fraudStmt->execute([$order['ip_address']]);
        $fraudData = $fraudStmt->fetch();
    }

    $order['fraud_score'] = $fraudData ? (int)$fraudData['fraud_score'] : null;
    $order['fraud_risk_level'] = $fraudData ? $fraudData['fraud_risk_level'] : null;
    $order['fraud_flags'] = $fraudData ? $fraudData['fraud_flags'] : null;

    // Find matching traffic record for this order
    $trafficData = null;
    $trafficEvents = [];
    $funnelJourney = [];

    // Method 1: Find upsell record with order_id_global matching this order ID
    $matchStmt = $pdo->prepare("SELECT sessid2, session_uuid, aff_id FROM affiliate_traffic WHERE page_type = 'upsell' AND page_url LIKE ? LIMIT 1");
    $matchStmt->execute(['%order_id_global=' . $order['order_id'] . '%']);
    $upsellMatch = $matchStmt->fetch();

    if ($upsellMatch) {
        // Find the landing record via sessid2 or session_uuid + aff_id
        if (!empty($upsellMatch['sessid2'])) {
            $tStmt = $pdo->prepare("SELECT * FROM affiliate_traffic WHERE sessid2 = ? AND aff_id = ? AND page_type = 'landing' LIMIT 1");
            $tStmt->execute([$upsellMatch['sessid2'], $upsellMatch['aff_id']]);
            $trafficData = $tStmt->fetch();
        }
        if (!$trafficData && !empty($upsellMatch['session_uuid'])) {
            $tStmt = $pdo->prepare("SELECT * FROM affiliate_traffic WHERE session_uuid = ? AND aff_id = ? AND page_type = 'landing' LIMIT 1");
            $tStmt->execute([$upsellMatch['session_uuid'], $upsellMatch['aff_id']]);
            $trafficData = $tStmt->fetch();
        }
    }

    // Method 2: Fallback - match by IP + affiliate_id
    if (!$trafficData && !empty($order['ip_address']) && !empty($order['affiliate_id'])) {
        $tStmt = $pdo->prepare("SELECT * FROM affiliate_traffic WHERE ip_address = ? AND aff_id = ? AND page_type = 'landing' ORDER BY timestamp DESC LIMIT 1");
        $tStmt->execute([$order['ip_address'], $order['affiliate_id']]);
        $trafficData = $tStmt->fetch();
    }

    // If traffic found, get events and funnel journey
    if ($trafficData) {
        $evStmt = $pdo->prepare("SELECT event_type, event_data, timestamp FROM user_behavior_events WHERE traffic_id = ? ORDER BY timestamp ASC");
        $evStmt->execute([$trafficData['id']]);
        $trafficEvents = $evStmt->fetchAll();
        foreach ($trafficEvents as &$ev) {
            $ev['event_data'] = json_decode($ev['event_data'], true);
        }

        // Funnel journey
        if (!empty($trafficData['sessid2'])) {
            $fjStmt = $pdo->prepare("SELECT id, page_type, page_url, aff_id, timestamp, max_scroll_depth, total_clicks, session_duration FROM affiliate_traffic WHERE sessid2 = ? ORDER BY timestamp ASC");
            $fjStmt->execute([$trafficData['sessid2']]);
            $funnelJourney = $fjStmt->fetchAll();
        }
        if (empty($funnelJourney) && !empty($trafficData['session_uuid'])) {
            $fjStmt = $pdo->prepare("SELECT id, page_type, page_url, aff_id, timestamp, max_scroll_depth, total_clicks, session_duration FROM affiliate_traffic WHERE session_uuid = ? ORDER BY timestamp ASC");
            $fjStmt->execute([$trafficData['session_uuid']]);
            $funnelJourney = $fjStmt->fetchAll();
        }
    }

    // Add tool fraud score to traffic data
    if ($trafficData) {
        $trafficData['tool_fraud_score'] = calculateToolFraudScore($trafficData);
    }

    echo json_encode([
        'success' => true,
        'order' => $order,
        'traffic' => $trafficData,
        'trafficEvents' => $trafficEvents,
        'funnelJourney' => $funnelJourney
    ]);
}

function saveOrderFulfillment($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId = $data['order_id'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $fulfillmentStatus = $data['fulfillment_status'] ?? 'pending';
    $delayReason = $data['delay_reason'] ?? null;
    $expectedDelivery = $data['expected_delivery'] ?? null;
    $deliveredDate = $data['delivered_date'] ?? null;
    $compensationOffered = intval($data['compensation_offered'] ?? 0);
    $compensationAmount = floatval($data['compensation_amount'] ?? 0);
    $compensationNotes = $data['compensation_notes'] ?? null;
    $internalNotes = $data['internal_notes'] ?? null;
    $delayMailSent = intval($data['delay_mail_sent'] ?? 0);

    $stmt = $pdo->prepare("UPDATE orders SET
        fulfillment_status = ?,
        delay_reason = ?,
        expected_delivery = ?,
        delivered_date = ?,
        compensation_offered = ?,
        compensation_amount = ?,
        compensation_notes = ?,
        internal_notes = ?,
        delay_mail_sent = ?
        WHERE order_id = ? AND domain_id = ?");

    $stmt->execute([
        $fulfillmentStatus,
        $delayReason,
        $expectedDelivery ?: null,
        $deliveredDate ?: null,
        $compensationOffered,
        $compensationAmount,
        $compensationNotes,
        $internalNotes,
        $delayMailSent,
        $orderId,
        $domainId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Fulfillment details saved'
    ]);
}

function updateFulfillmentProgress($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId = $data['order_id'] ?? '';
    $stage = $data['stage'] ?? '';

    if (empty($orderId) || empty($domainId) || empty($stage)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id, domain_id, or stage']);
        return;
    }

    if ($stage === 'sent_to_fulfillment') {
        $fulfillmentHouse = $data['fulfillment_house'] ?? '';
        $stmt = $pdo->prepare("UPDATE orders SET sent_to_fulfillment = 1, fulfillment_name = ? WHERE order_id = ? AND domain_id = ?");
        $stmt->execute([$fulfillmentHouse ?: null, $orderId, $domainId]);
        echo json_encode(['success' => true, 'message' => 'Marked as sent to fulfillment']);
    } elseif ($stage === 'dispatched') {
        $tracking = $data['tracking_number'] ?? '';
        $stmt = $pdo->prepare("UPDATE orders SET fulfillment_status = 'shipped', dispatch_tracking = ? WHERE order_id = ? AND domain_id = ?");
        $stmt->execute([$tracking ?: null, $orderId, $domainId]);
        echo json_encode(['success' => true, 'message' => 'Marked as dispatched']);
    } elseif ($stage === 'delivered') {
        $stmt = $pdo->prepare("UPDATE orders SET fulfillment_status = 'delivered', delivered_date = CURDATE() WHERE order_id = ? AND domain_id = ?");
        $stmt->execute([$orderId, $domainId]);
        echo json_encode(['success' => true, 'message' => 'Marked as delivered']);
    } else {
        echo json_encode(['error' => 'Invalid stage: ' . $stage]);
    }
}

function toggleDelayMail($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId = $data['order_id'] ?? '';
    $checked = intval($data['checked'] ?? 0);

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    // Get customer email from the source order
    $stmt = $pdo->prepare("SELECT customer_email FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['customer_email'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        return;
    }

    // Update ALL delayed orders with same customer email in this domain
    $stmt = $pdo->prepare("UPDATE orders SET delay_mail_sent = ?
        WHERE domain_id = ? AND customer_email = ? AND fulfillment_status = 'delayed'");
    $stmt->execute([$checked, $domainId, $row['customer_email']]);
    $affected = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'updated' => $affected
    ]);
}

// ================================================================
// SEND DELAY EMAIL
// ================================================================

function getDelayedOrdersForCustomer($pdo, $domainId, $orderId) {
    $stmt = $pdo->prepare("SELECT customer_email FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['customer_email'])) return null;

    $stmt = $pdo->prepare("SELECT * FROM orders
        WHERE domain_id = ? AND customer_email = ? AND fulfillment_status = 'delayed'
        ORDER BY flag_upsell ASC, date_created ASC");
    $stmt->execute([$domainId, $row['customer_email']]);
    return $stmt->fetchAll();
}

function previewDelayMail($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId = $data['order_id'] ?? '';
    $deliveryStart = $data['delivery_start'] ?? '';
    $deliveryEnd   = $data['delivery_end'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $orders = getDelayedOrdersForCustomer($pdo, $domainId, $orderId);
    if (empty($orders)) {
        echo json_encode(['success' => false, 'error' => 'No delayed orders found']);
        return;
    }

    $html = buildDelayMailFromOrders($orders, $deliveryStart, $deliveryEnd);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'customer_email' => $orders[0]['customer_email'],
        'customer_name'  => $orders[0]['customer_name']
    ]);
}

function sendDelayMailAction($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId      = $data['domain_id'] ?? null;
    $orderId       = $data['order_id'] ?? '';
    $recipients    = $data['recipients'] ?? [];
    $deliveryStart = $data['delivery_start'] ?? '';
    $deliveryEnd   = $data['delivery_end'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $orders = getDelayedOrdersForCustomer($pdo, $domainId, $orderId);
    if (empty($orders)) {
        echo json_encode(['success' => false, 'error' => 'No delayed orders found for this customer']);
        return;
    }

    $email = $orders[0]['customer_email'];

    // Use provided recipients or fall back to customer email
    if (empty($recipients)) {
        $recipients = [$email];
    }

    $result = sendDelayMail($orders, $recipients, $deliveryStart, $deliveryEnd);

    if ($result['success']) {
        // Mark all delayed orders for this customer as mail sent
        $stmt = $pdo->prepare("UPDATE orders SET delay_mail_sent = 1
            WHERE domain_id = ? AND customer_email = ? AND fulfillment_status = 'delayed'");
        $stmt->execute([$domainId, $email]);

        echo json_encode([
            'success' => true,
            'message' => 'Email sent to ' . implode(', ', $result['sent_to']),
            'orders_updated' => $stmt->rowCount()
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
}

// ================================================================
// REORDER REMINDER EMAIL
// ================================================================

function previewReorderMail($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId = $data['order_id'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $coupon = [];
    if (!empty($data['coupon_code'])) {
        $coupon = [
            'code'   => $data['coupon_code'],
            'type'   => $data['coupon_type'] ?? 'percentage',
            'amount' => $data['coupon_amount'] ?? 0
        ];
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $html = buildReorderMailFromOrder($order, $coupon);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'customer_email' => $order['customer_email'],
        'customer_name'  => $order['customer_name'],
        'customer_state' => $order['state'] ?? '',
        'date_created'   => $order['date_created'] ?? ''
    ]);
}

function sendReorderMailAction($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId   = $data['domain_id'] ?? null;
    $orderId    = $data['order_id'] ?? '';
    $recipients = $data['recipients'] ?? [];

    $coupon = [];
    if (!empty($data['coupon_code'])) {
        $coupon = [
            'code'   => $data['coupon_code'],
            'type'   => $data['coupon_type'] ?? 'percentage',
            'amount' => $data['coupon_amount'] ?? 0
        ];
    }

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    if (empty($recipients)) {
        $recipients = [$order['customer_email']];
    }

    $result = sendReorderMail($order, $recipients, $coupon);

    if ($result['success']) {
        // Mark this order as reorder mail sent
        $stmt = $pdo->prepare("UPDATE orders SET reorder_mail_sent = 1 WHERE order_id = ? AND domain_id = ?");
        $stmt->execute([$orderId, $domainId]);

        // Log the send
        $stmt = $pdo->prepare("INSERT INTO reorder_mail_log (domain_id, order_id, recipients, coupon_code, coupon_type, coupon_amount, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $domainId, $orderId,
            implode(', ', $result['sent_to']),
            $coupon['code'] ?? null,
            $coupon['type'] ?? null,
            !empty($coupon['amount']) ? $coupon['amount'] : null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Reorder email sent to ' . implode(', ', $result['sent_to'])
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
}

function getReorderHistory($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId  = $data['order_id'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        return;
    }

    $stmt = $pdo->prepare("SELECT recipients, coupon_code, coupon_type, coupon_amount, sent_at
        FROM reorder_mail_log WHERE domain_id = ? AND order_id = ? ORDER BY sent_at DESC LIMIT 20");
    $stmt->execute([$domainId, $orderId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'history' => $rows]);
}

// ================================================================
// DISPATCH STATUS EMAIL
// ================================================================

function previewDispatchMail($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId     = $data['domain_id'] ?? null;
    $orderId      = $data['order_id'] ?? '';
    $dispatchType = $data['dispatch_type'] ?? 'other';

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $html = buildDispatchMailFromOrder($order, $dispatchType);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'customer_email' => $order['customer_email'],
        'customer_name'  => $order['customer_name']
    ]);
}

function sendDispatchMailAction($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId        = $data['domain_id'] ?? null;
    $orderId         = $data['order_id'] ?? '';
    $dispatchType    = $data['dispatch_type'] ?? 'other';
    $recipients      = $data['recipients'] ?? [];
    $fulfillmentName = trim($data['fulfillment_name'] ?? '');

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    if (empty($recipients)) {
        $recipients = [$order['customer_email']];
    }

    $result = sendDispatchMail($order, $recipients, $dispatchType);

    if ($result['success']) {
        // Update fulfillment status + fulfillment name
        $newStatus = $dispatchType === 'same' ? 'shipped' : 'delivered';
        if (!empty($fulfillmentName)) {
            $stmt = $pdo->prepare("UPDATE orders SET fulfillment_status = ?, fulfillment_name = ? WHERE order_id = ? AND domain_id = ?");
            $stmt->execute([$newStatus, $fulfillmentName, $orderId, $domainId]);
        } else {
            $stmt = $pdo->prepare("UPDATE orders SET fulfillment_status = ? WHERE order_id = ? AND domain_id = ?");
            $stmt->execute([$newStatus, $orderId, $domainId]);
        }

        $typeLabel = $dispatchType === 'same' ? 'Dispatched' : 'Fulfilled with other house';
        echo json_encode([
            'success' => true,
            'message' => 'Status updated to ' . $typeLabel . '. Email sent to ' . implode(', ', $result['sent_to'])
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
}

// ================================================================
// COMPENSATION EMAIL
// ================================================================

function previewCompensationMail($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId     = $data['domain_id'] ?? null;
    $orderId      = $data['order_id'] ?? '';
    $couponCode   = $data['coupon_code'] ?? '';
    $usageType    = $data['usage_type'] ?? 'one-time';
    $limitedCount = $data['limited_count'] ?? '';
    $productName  = $data['product_name'] ?? 'MetaTrim BHB';
    $productLink  = $data['product_link'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing order_id or domain_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $html = buildCompensationMailFromOrder($order, $couponCode, $usageType, $limitedCount, $productName, $productLink);

    echo json_encode([
        'success' => true,
        'html' => $html,
        'customer_email' => $order['customer_email'],
        'customer_name'  => $order['customer_name']
    ]);
}

function sendCompensationMailAction($pdo) {
    require_once __DIR__ . '/mailer.php';

    $data = getPostData();
    $domainId     = $data['domain_id'] ?? null;
    $orderId      = $data['order_id'] ?? '';
    $recipients   = $data['recipients'] ?? [];
    $couponCode   = $data['coupon_code'] ?? '';
    $usageType    = $data['usage_type'] ?? 'one-time';
    $limitedCount = $data['limited_count'] ?? '';
    $productName  = $data['product_name'] ?? 'MetaTrim BHB';
    $productLink  = $data['product_link'] ?? '';

    if (empty($orderId) || empty($domainId) || empty($couponCode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    if (empty($recipients)) {
        $recipients = [$order['customer_email']];
    }

    $result = sendCompensationMail($order, $recipients, $couponCode, $usageType, $limitedCount, $productName, $productLink);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Compensation email sent to ' . implode(', ', $result['sent_to'])
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
}

// ================================================================
// SCHEDULE REORDER MAIL
// ================================================================

function scheduleReorderMailAction($pdo) {
    $data = getPostData();
    $domainId    = $data['domain_id'] ?? null;
    $orderId     = $data['order_id'] ?? '';
    $recipients  = $data['recipients'] ?? [];
    $scheduledAt = $data['scheduled_at'] ?? ''; // PKT datetime string

    if (empty($orderId) || empty($domainId) || empty($scheduledAt)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    if (empty($recipients)) {
        $recipients = [$order['customer_email']];
    }

    $coupon = [];
    if (!empty($data['coupon_code'])) {
        $coupon = [
            'code'   => $data['coupon_code'],
            'type'   => $data['coupon_type'] ?? 'percentage',
            'amount' => $data['coupon_amount'] ?? 0
        ];
    }

    // Store scheduled email
    $stmt = $pdo->prepare("INSERT INTO scheduled_emails (domain_id, order_id, email_type, recipients, coupon_code, coupon_type, coupon_amount, scheduled_at)
        VALUES (?, ?, 'reorder', ?, ?, ?, ?, ?)");
    $stmt->execute([$domainId, $orderId, json_encode($recipients),
        $coupon['code'] ?? null, $coupon['type'] ?? null,
        !empty($coupon['amount']) ? $coupon['amount'] : null,
        $scheduledAt]);

    // Mark order as reorder mail sent (scheduled)
    $stmt = $pdo->prepare("UPDATE orders SET reorder_mail_sent = 1 WHERE order_id = ? AND domain_id = ?");
    $stmt->execute([$orderId, $domainId]);

    // Log the scheduled send
    $stmt = $pdo->prepare("INSERT INTO reorder_mail_log (domain_id, order_id, recipients, coupon_code, coupon_type, coupon_amount, sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $domainId, $orderId,
        implode(', ', $recipients),
        $coupon['code'] ?? null,
        $coupon['type'] ?? null,
        !empty($coupon['amount']) ? $coupon['amount'] : null,
        $scheduledAt
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Reorder email scheduled for ' . $scheduledAt . ' PKT'
    ]);
}

// ================================================================
// NOTIFICATIONS (Dashboard feed)
// ================================================================

function getNotifications($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $limit = intval($data['limit'] ?? 20);

    if (empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id']);
        return;
    }

    $notifications = [];

    // 1. Reorder reminders — customer supply ending within 5 days (includes upsell bottles)
    $stmt = $pdo->prepare("SELECT order_id, customer_name, customer_email, product_names, date_created, reorder_mail_sent
        FROM orders WHERE domain_id = ? AND flag_upsell = 0 AND was_canceled = 0 AND is_test = 0
        AND date_created >= '2026-01-13'
        ORDER BY date_created ASC");
    $stmt->execute([$domainId]);
    $mainOrders = $stmt->fetchAll();

    // Build upsell lookup by customer email
    $stmtUp = $pdo->prepare("SELECT customer_email, product_names FROM orders
        WHERE domain_id = ? AND flag_upsell = 1 AND was_canceled = 0 AND is_test = 0
        AND date_created >= '2026-01-13'");
    $stmtUp->execute([$domainId]);
    $upsellMap = [];
    foreach ($stmtUp->fetchAll() as $uo) {
        $email = strtolower($uo['customer_email']);
        if (!isset($upsellMap[$email])) $upsellMap[$email] = $uo['product_names'];
    }

    foreach ($mainOrders as $o) {
        $bottles = 1;
        if (preg_match('/(\d+)\s*\+\s*(\d+)/', $o['product_names'], $m)) {
            $bottles = intval($m[1]) + intval($m[2]);
        } elseif (preg_match('/(\d+)\s*Bottle/i', $o['product_names'], $m)) {
            $bottles = intval($m[1]);
        }
        $mainDays = $bottles * 30;

        // Add upsell days
        $upsellDays = 0;
        $custEmail = strtolower($o['customer_email'] ?? '');
        if ($custEmail && isset($upsellMap[$custEmail])) {
            $uBottles = 1;
            if (preg_match('/(\d+)\s*\+\s*(\d+)/', $upsellMap[$custEmail], $um)) {
                $uBottles = intval($um[1]) + intval($um[2]);
            } elseif (preg_match('/(\d+)\s*Bottle/i', $upsellMap[$custEmail], $um)) {
                $uBottles = intval($um[1]);
            }
            $upsellDays = $uBottles * 30;
        }

        $totalSupply = $mainDays + $upsellDays;
        $daysSince = (int)((time() - strtotime($o['date_created'])) / 86400);
        $daysLeft = $totalSupply - $daysSince;

        if ($daysLeft <= 5 && $daysLeft >= -10 && $o['reorder_mail_sent'] != 1) {
            $supplyText = $upsellDays > 0
                ? ($mainDays - $daysSince) . 'd + ' . $upsellDays . 'd'
                : $daysLeft . ' days';
            $desc = $daysLeft <= 0
                ? $o['customer_name'] . '\'s ' . $o['product_names'] . ' supply expired ' . abs($daysLeft) . ' days ago. Please send the ReOrder email.'
                : $o['customer_name'] . ' has ' . $supplyText . ' left of ' . $o['product_names'] . '. Please send the Customer ReOrder Email.';
            $notifications[] = [
                'type' => 'reorder_due',
                'icon' => 'fa-redo',
                'color' => '#d97706',
                'title' => 'Supply Ending — ' . $o['customer_name'],
                'desc' => $desc,
                'time' => date('Y-m-d H:i:s'),
                'order_id' => $o['order_id']
            ];
        }
    }

    // 2. Scheduled emails pending
    $stmt = $pdo->prepare("SELECT se.*, o.customer_name FROM scheduled_emails se
        LEFT JOIN orders o ON o.order_id = se.order_id AND o.domain_id = se.domain_id
        WHERE se.domain_id = ? AND se.status = 'pending'
        ORDER BY se.scheduled_at ASC LIMIT 10");
    $stmt->execute([$domainId]);
    foreach ($stmt->fetchAll() as $se) {
        $notifications[] = [
            'type' => 'scheduled',
            'icon' => 'fa-clock',
            'color' => '#3498db',
            'title' => 'Scheduled Email — ' . ($se['customer_name'] ?? 'Order #' . $se['order_id']),
            'desc' => ucfirst($se['email_type']) . ' email scheduled for ' . date('M j, g:i A', strtotime($se['scheduled_at'])) . ' PKT',
            'time' => $se['created_at']
        ];
    }

    // 3. Delayed orders — customer not yet notified (includes auto-detected delay window orders)
    $stmt = $pdo->prepare("SELECT order_id, customer_name, customer_email, product_names, total_amount, date_created
        FROM orders WHERE domain_id = ? AND delay_mail_sent = 0
        AND (fulfillment_status = 'delayed' OR (DATE(date_created) >= '2026-02-24' AND DATE(date_created) <= '2026-03-13' AND (fulfillment_status IS NULL OR fulfillment_status = '' OR fulfillment_status = 'pending')))
        ORDER BY date_created DESC LIMIT 10");
    $stmt->execute([$domainId]);
    foreach ($stmt->fetchAll() as $o) {
        $orderDate = date('M j, Y', strtotime($o['date_created']));
        $notifications[] = [
            'type' => 'delay_unsent',
            'icon' => 'fa-exclamation-triangle',
            'color' => '#e74c3c',
            'title' => 'Order Delayed — ' . $o['customer_name'],
            'desc' => 'Order #' . $o['order_id'] . ' ($' . number_format($o['total_amount'], 2) . ' — ' . $o['product_names'] . ') placed on ' . $orderDate . ' is delayed due to JetPack fulfilment pause. Please send the Order Delay Email or attempt another fulfilment.',
            'time' => $o['date_created'],
            'order_id' => $o['order_id']
        ];
    }

    // 4. Recently sent emails (from scheduled_emails)
    $stmt = $pdo->prepare("SELECT se.*, o.customer_name FROM scheduled_emails se
        LEFT JOIN orders o ON o.order_id = se.order_id AND o.domain_id = se.domain_id
        WHERE se.domain_id = ? AND se.status = 'sent'
        ORDER BY se.sent_at DESC LIMIT 20");
    $stmt->execute([$domainId]);
    foreach ($stmt->fetchAll() as $se) {
        $notifications[] = [
            'type' => 'email_sent',
            'icon' => 'fa-check-circle',
            'color' => '#27ae60',
            'title' => 'Email Sent — ' . ($se['customer_name'] ?? 'Order #' . $se['order_id']),
            'desc' => ucfirst($se['email_type']) . ' email delivered at ' . date('M j, g:i A', strtotime($se['sent_at'])) . ' PKT',
            'time' => $se['sent_at']
        ];
    }

    // Sort all by time descending
    usort($notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    echo json_encode([
        'success' => true,
        'data' => array_slice($notifications, 0, $limit)
    ]);
}

// ================================================================
// CHECKOUT LEADS (Isolated Feature)
// ================================================================

function logCheckoutLead($pdo) {
    $data = getPostData();

    $checkoutUUID = trim($data['checkout_uuid'] ?? '');
    if (empty($checkoutUUID)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing checkout_uuid']);
        return;
    }

    $sessid2 = trim($data['sessid2'] ?? '');
    $affId = trim($data['aff_id'] ?? '');
    $subId = trim($data['sub_id'] ?? '');
    $accountId = trim($data['account_id'] ?? '');
    $productCodename = trim($data['product_codename'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $fullName = trim($data['full_name'] ?? '');
    $address = trim($data['address'] ?? '');
    $country = trim($data['country'] ?? '');
    $state = trim($data['state'] ?? '');
    $city = trim($data['city'] ?? '');
    $zip = trim($data['zip'] ?? '');
    $paymentMethod = trim($data['payment_method'] ?? '');
    $status = trim($data['status'] ?? 'started');
    $checkoutUrl = trim($data['checkout_url'] ?? '');
    $referrer = trim($data['referrer'] ?? '');

    // Get client IP
    $ipAddress = '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ipAddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ipAddress = trim($parts[0]);
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    }

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // UPSERT: create or update based on checkout_uuid
    $stmt = $pdo->prepare("
        INSERT INTO checkout_leads
            (checkout_uuid, sessid2, aff_id, sub_id, account_id, product_codename,
             email, phone, full_name, address, country, state, city, zip,
             payment_method, status, ip_address, user_agent, checkout_url, referrer)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            email = IF(? != '', ?, email),
            phone = IF(? != '', ?, phone),
            full_name = IF(? != '', ?, full_name),
            address = IF(? != '', ?, address),
            country = IF(? != '', ?, country),
            state = IF(? != '', ?, state),
            city = IF(? != '', ?, city),
            zip = IF(? != '', ?, zip),
            payment_method = IF(? != '', ?, payment_method),
            status = CASE
                WHEN ? = 'purchase_attempted' THEN 'purchase_attempted'
                WHEN status = 'purchase_attempted' THEN status
                WHEN ? = 'form_filled' THEN 'form_filled'
                ELSE status
            END,
            last_updated = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        // INSERT values
        $checkoutUUID, $sessid2, $affId, $subId, $accountId, $productCodename,
        $email, $phone, $fullName, $address, $country, $state, $city, $zip,
        $paymentMethod, $status, $ipAddress, $userAgent, $checkoutUrl, $referrer,
        // ON DUPLICATE KEY UPDATE values
        $email, $email,
        $phone, $phone,
        $fullName, $fullName,
        $address, $address,
        $country, $country,
        $state, $state,
        $city, $city,
        $zip, $zip,
        $paymentMethod, $paymentMethod,
        $status,
        $status
    ]);

    // Return response quickly
    echo json_encode(['success' => true]);

    // Finish response, then do background work if needed
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

function markCheckoutCompleted($pdo) {
    $data = getPostData();

    // Can match by checkout_uuid or sessid2
    $checkoutUUID = trim($data['checkout_uuid'] ?? '');
    $sessid2 = trim($data['sessid2'] ?? '');

    if (empty($checkoutUUID) && empty($sessid2)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing checkout_uuid or sessid2']);
        return;
    }

    if (!empty($checkoutUUID)) {
        $stmt = $pdo->prepare("UPDATE checkout_leads SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE checkout_uuid = ?");
        $stmt->execute([$checkoutUUID]);
    } else {
        $stmt = $pdo->prepare("UPDATE checkout_leads SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE sessid2 = ? AND status != 'completed'");
        $stmt->execute([$sessid2]);
    }

    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
}

function getCheckoutLeads($pdo) {
    $data = getPostData();

    $page = max(1, intval($data['page'] ?? 1));
    $limit = min(100, max(10, intval($data['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    $search = trim($data['search'] ?? '');
    $status = trim($data['status'] ?? '');
    $accountId = trim($data['account_id'] ?? '');
    $dateFrom = trim($data['date_from'] ?? '');
    $dateTo = trim($data['date_to'] ?? '');

    $where = '1=1';
    $params = [];

    if (!empty($search)) {
        $where .= ' AND (email LIKE ? OR phone LIKE ? OR full_name LIKE ?)';
        $searchLike = '%' . $search . '%';
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
    }
    if (!empty($status)) {
        $where .= ' AND status = ?';
        $params[] = $status;
    }
    if (!empty($accountId)) {
        $where .= ' AND account_id = ?';
        $params[] = $accountId;
    }
    if (!empty($dateFrom)) {
        $where .= ' AND started_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if (!empty($dateTo)) {
        $where .= ' AND started_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM checkout_leads WHERE $where");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Data
    $dataParams = array_merge($params, [$limit, $offset]);
    $dataStmt = $pdo->prepare("SELECT * FROM checkout_leads WHERE $where ORDER BY started_at DESC LIMIT ? OFFSET ?");
    $dataStmt->execute($dataParams);
    $rows = $dataStmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows, 'total' => intval($total)]);
}

function getCheckoutStats($pdo) {
    $data = getPostData();

    $accountId = trim($data['account_id'] ?? '');
    $dateFrom = trim($data['date_from'] ?? '');
    $dateTo = trim($data['date_to'] ?? '');

    $where = '1=1';
    $params = [];

    if (!empty($accountId)) {
        $where .= ' AND account_id = ?';
        $params[] = $accountId;
    }
    if (!empty($dateFrom)) {
        $where .= ' AND started_at >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if (!empty($dateTo)) {
        $where .= ' AND started_at <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_leads,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'purchase_attempted' THEN 1 ELSE 0 END) as purchase_attempted,
            SUM(CASE WHEN status = 'form_filled' THEN 1 ELSE 0 END) as form_filled,
            SUM(CASE WHEN status = 'started' THEN 1 ELSE 0 END) as started
        FROM checkout_leads
        WHERE $where
    ");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    // Top products
    $prodStmt = $pdo->prepare("
        SELECT product_codename, COUNT(*) as cnt,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM checkout_leads
        WHERE $where AND product_codename IS NOT NULL AND product_codename != ''
        GROUP BY product_codename
        ORDER BY cnt DESC
        LIMIT 10
    ");
    $prodStmt->execute($params);
    $products = $prodStmt->fetchAll();

    $totalLeads = intval($stats['total_leads']);
    $completedCount = intval($stats['completed']);
    $conversionRate = $totalLeads > 0 ? round(($completedCount / $totalLeads) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_leads' => $totalLeads,
            'completed' => $completedCount,
            'purchase_attempted' => intval($stats['purchase_attempted']),
            'form_filled' => intval($stats['form_filled']),
            'started' => intval($stats['started']),
            'conversion_rate' => $conversionRate
        ],
        'products' => $products
    ]);
}

// ================================================================
// SHAVE SNAPSHOTS — Before/After Cookie Comparison (matched by IP+UA)
// ================================================================
function logShaveSnapshot($pdo) {
    $data = getPostData();
    $phase = $data['phase'] ?? '';
    $domainId = $data['domain_id'] ?? 0;
    $ip = getClientIP();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $snapToken = $data['snap_token'] ?? '';

    if ($phase === 'before') {
        // Delete any pending (unmatched) BEFORE snapshots for this IP+UA to prevent duplicates
        $pdo->prepare("
            DELETE FROM shave_snapshots
            WHERE ip_address = ? AND LEFT(user_agent, 100) = LEFT(?, 100)
              AND domain_id = ? AND after_at IS NULL
        ")->execute([$ip, substr($ua, 0, 512), $domainId]);

        // Insert new BEFORE snapshot
        $stmt = $pdo->prepare("
            INSERT INTO shave_snapshots
            (domain_id, session_id, ip_address, user_agent, aff_id, sub_id, mode,
             replace_aff_id, replace_sub_id, platform, snap_token,
             before_url, before_sessid2, before_cookies, before_cookie_count, before_url_params, before_checkout_urls, before_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $domainId,
            $data['session_id'] ?? null,
            $ip,
            substr($ua, 0, 512),
            $data['aff_id'] ?? '',
            $data['sub_id'] ?? '',
            $data['mode'] ?? 'remove',
            $data['replace_aff_id'] ?? '',
            $data['replace_sub_id'] ?? '',
            $data['platform'] ?? 'buygoods',
            $snapToken,
            $data['url'] ?? '',
            $data['sessid2'] ?? '',
            $data['cookies'] ?? '{}',
            $data['cookie_count'] ?? 0,
            $data['url_params'] ?? '{}',
            $data['checkout_urls'] ?? '[]'
        ]);
        echo json_encode(['success' => true, 'snapshot_id' => $pdo->lastInsertId()]);

    } elseif ($phase === 'after') {
        // Try matching by snap_token first (reliable), fall back to IP+UA
        $row = null;
        if ($snapToken) {
            $stmt = $pdo->prepare("
                SELECT id, before_sessid2, before_cookies FROM shave_snapshots
                WHERE snap_token = ? AND domain_id = ? AND after_at IS NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$snapToken, $domainId]);
            $row = $stmt->fetch();
        }
        if (!$row) {
            // Fallback: match by IP+UA
            $stmt = $pdo->prepare("
                SELECT id, before_sessid2, before_cookies FROM shave_snapshots
                WHERE ip_address = ? AND LEFT(user_agent, 100) = LEFT(?, 100)
                  AND domain_id = ? AND after_at IS NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$ip, substr($ua, 0, 512), $domainId]);
            $row = $stmt->fetch();
        }

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'No pending BEFORE snapshot found']);
            return;
        }

        // Compute diff
        $beforeCookies = json_decode($row['before_cookies'], true) ?: [];
        $afterCookies = json_decode($data['cookies'] ?? '{}', true) ?: [];
        $beforeSessid2 = $row['before_sessid2'] ?? '';
        $afterSessid2 = $data['sessid2'] ?? '';

        $added = [];
        $removed = [];
        $changed = [];

        foreach ($afterCookies as $k => $v) {
            if (!isset($beforeCookies[$k])) {
                $added[$k] = $v;
            } elseif ($beforeCookies[$k] !== $v) {
                $changed[$k] = ['before' => $beforeCookies[$k], 'after' => $v];
            }
        }
        foreach ($beforeCookies as $k => $v) {
            if (!isset($afterCookies[$k])) {
                $removed[$k] = $v;
            }
        }

        $update = $pdo->prepare("
            UPDATE shave_snapshots SET
                after_url = ?,
                after_sessid2 = ?,
                after_cookies = ?,
                after_cookie_count = ?,
                after_url_params = ?,
                after_checkout_urls = ?,
                after_at = NOW(),
                sessid2_changed = ?,
                cookies_added = ?,
                cookies_removed = ?,
                cookies_changed = ?
            WHERE id = ?
        ");
        $update->execute([
            $data['url'] ?? '',
            $afterSessid2,
            $data['cookies'] ?? '{}',
            $data['cookie_count'] ?? 0,
            $data['url_params'] ?? '{}',
            $data['checkout_urls'] ?? '[]',
            ($beforeSessid2 !== $afterSessid2) ? 1 : 0,
            json_encode($added),
            json_encode($removed),
            json_encode($changed),
            $row['id']
        ]);

        echo json_encode(['success' => true, 'snapshot_id' => $row['id'], 'sessid2_changed' => ($beforeSessid2 !== $afterSessid2)]);

    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid phase: must be before or after']);
    }
}

function deleteShaveSnapshots($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'domain_id required']);
        return;
    }
    $stmt = $pdo->prepare("DELETE FROM shave_snapshots WHERE domain_id = ?");
    $stmt->execute([$domainId]);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
}

function getShaveSnapshots($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $limit = min(intval($data['limit'] ?? 50), 200);
    $offset = intval($data['offset'] ?? 0);
    $sessionId = $data['session_id'] ?? null;

    $where = '1=1';
    $params = [];

    if ($domainId) {
        $where .= ' AND s.domain_id = ?';
        $params[] = $domainId;
    }
    if ($sessionId) {
        $where .= ' AND s.session_id = ?';
        $params[] = $sessionId;
    }

    // Count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM shave_snapshots s WHERE $where");
    $countStmt->execute($params);
    $total = intval($countStmt->fetchColumn());

    // Fetch
    $params[] = $limit;
    $params[] = $offset;
    $stmt = $pdo->prepare("
        SELECT s.*
        FROM shave_snapshots s
        WHERE $where
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $snapshots = [];
    foreach ($rows as $r) {
        $snapshots[] = [
            'id' => $r['id'],
            'domainId' => $r['domain_id'],
            'sessionId' => $r['session_id'],
            'ip' => $r['ip_address'],
            'affId' => $r['aff_id'],
            'subId' => $r['sub_id'],
            'mode' => $r['mode'],
            'replaceAffId' => $r['replace_aff_id'],
            'replaceSubId' => $r['replace_sub_id'],
            'platform' => $r['platform'],
            'before' => [
                'url' => $r['before_url'],
                'sessid2' => $r['before_sessid2'],
                'cookies' => json_decode($r['before_cookies'], true) ?: [],
                'cookieCount' => $r['before_cookie_count'],
                'urlParams' => json_decode($r['before_url_params'], true) ?: [],
                'checkoutUrls' => json_decode($r['before_checkout_urls'] ?? '[]', true) ?: [],
                'at' => $r['before_at']
            ],
            'after' => $r['after_at'] ? [
                'url' => $r['after_url'],
                'sessid2' => $r['after_sessid2'],
                'cookies' => json_decode($r['after_cookies'], true) ?: [],
                'cookieCount' => $r['after_cookie_count'],
                'urlParams' => json_decode($r['after_url_params'], true) ?: [],
                'checkoutUrls' => json_decode($r['after_checkout_urls'] ?? '[]', true) ?: [],
                'at' => $r['after_at']
            ] : null,
            'sessid2Changed' => (bool)$r['sessid2_changed'],
            'cookiesAdded' => json_decode($r['cookies_added'], true) ?: [],
            'cookiesRemoved' => json_decode($r['cookies_removed'], true) ?: [],
            'cookiesChanged' => json_decode($r['cookies_changed'], true) ?: [],
            'createdAt' => $r['created_at']
        ];
    }

    echo json_encode(['success' => true, 'data' => $snapshots, 'total' => $total]);
}

// ============================================================
// Affiliate Report — Country-filterable traffic performance
// ============================================================
function getReportCountries($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $domainFilter = !empty($domainId) ? " AND domain_id = " . (int)$domainId : "";

    $stmt = $pdo->query("
        SELECT country, country_code, COUNT(*) as visits
        FROM affiliate_traffic
        WHERE country IS NOT NULL AND country != '' AND country_code IS NOT NULL AND country_code != '' $domainFilter
        GROUP BY country, country_code
        ORDER BY visits DESC
    ");
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'countries' => $countries]);
}

function getAffiliateReport($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? '';
    $period = $data['period'] ?? $_GET['period'] ?? 'today';
    $countryCode = $data['country'] ?? $_GET['country'] ?? '';

    $timeFilter = getTimeFilter($period);
    $domainFilter = !empty($domainId) ? " AND domain_id = " . (int)$domainId : "";
    $countryFilter = '';
    if (!empty($countryCode)) {
        $countryFilter = " AND country_code = '" . preg_replace('/[^A-Z]/', '', strtoupper($countryCode)) . "'";
    }
    $baseWhere = "$timeFilter $domainFilter $countryFilter";

    // 1. Summary totals (US only)
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_visits,
            COUNT(DISTINCT aff_id) as unique_affiliates,
            COUNT(DISTINCT ip_address) as unique_visitors,
            SUM(was_shaved) as shaved_visits,
            AVG(NULLIF(session_duration, 0)) as avg_duration,
            AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
            SUM(COALESCE(total_clicks, 0)) as total_clicks,
            SUM(COALESCE(redirect_clicks, 0)) as total_redirect_clicks,
            SUM(COALESCE(buynow_clicks, 0)) as total_buynow_clicks,
            SUM(COALESCE(footer_clicks, 0)) as total_footer_clicks,
            SUM(COALESCE(video_plays, 0)) as total_video_plays,
            SUM(COALESCE(cta_bar_clicks, 0)) as total_cta_bar_clicks,
            SUM(COALESCE(vsl_clicks, 0)) as total_vsl_clicks,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout,
            SUM(CASE WHEN session_duration > 5 THEN 1 ELSE 0 END) as qualified_visits,
            SUM(bounce) as bounce_count,
            AVG(NULLIF(redirect_time_ms, 0)) as avg_redirect_time_ms
        FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing'
    ");
    $stmt->execute();
    $totals = $stmt->fetch();

    // 2. Per-affiliate breakdown (US only, landing pages)
    $stmt = $pdo->prepare("
        SELECT
            aff_id,
            COUNT(*) as visits,
            COUNT(DISTINCT ip_address) as unique_visitors,
            SUM(was_shaved) as shaved,
            AVG(NULLIF(session_duration, 0)) as avg_duration,
            AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
            SUM(COALESCE(total_clicks, 0)) as total_clicks,
            SUM(COALESCE(redirect_clicks, 0)) as redirect_clicks,
            SUM(COALESCE(buynow_clicks, 0)) as buynow_clicks,
            SUM(COALESCE(footer_clicks, 0)) as footer_clicks,
            SUM(COALESCE(video_plays, 0)) as video_plays,
            SUM(COALESCE(cta_bar_clicks, 0)) as cta_bar_clicks,
            SUM(COALESCE(vsl_clicks, 0)) as vsl_clicks,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout,
            SUM(bounce) as bounces,
            SUM(CASE WHEN session_duration > 5 THEN 1 ELSE 0 END) as qualified,
            AVG(NULLIF(redirect_time_ms, 0)) as avg_redirect_time_ms,
            MIN(timestamp) as first_seen,
            MAX(timestamp) as last_seen
        FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing'
        GROUP BY aff_id
        ORDER BY visits DESC
        LIMIT 50
    ");
    $stmt->execute();
    $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Per-page engagement (US only, landing pages)
    $stmt = $pdo->prepare("
        SELECT
            SUBSTRING_INDEX(page_url, '?', 1) as page_url,
            COUNT(*) as visits,
            COUNT(DISTINCT ip_address) as unique_visitors,
            COUNT(DISTINCT aff_id) as affiliates_count,
            AVG(NULLIF(session_duration, 0)) as avg_duration,
            AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
            SUM(COALESCE(total_clicks, 0)) as total_clicks,
            SUM(COALESCE(redirect_clicks, 0)) as redirect_clicks,
            SUM(COALESCE(buynow_clicks, 0)) as buynow_clicks,
            SUM(COALESCE(footer_clicks, 0)) as footer_clicks,
            SUM(COALESCE(video_plays, 0)) as video_plays,
            SUM(COALESCE(cta_bar_clicks, 0)) as cta_bar_clicks,
            SUM(COALESCE(vsl_clicks, 0)) as vsl_clicks,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout,
            SUM(bounce) as bounces,
            SUM(CASE WHEN session_duration > 5 THEN 1 ELSE 0 END) as qualified
        FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing' AND page_url IS NOT NULL AND page_url != ''
        GROUP BY SUBSTRING_INDEX(page_url, '?', 1)
        ORDER BY visits DESC
        LIMIT 30
    ");
    $stmt->execute();
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Device breakdown (US only)
    $stmt = $pdo->prepare("
        SELECT device, COUNT(*) as count FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing' AND device IS NOT NULL AND device != ''
        GROUP BY device ORDER BY count DESC
    ");
    $stmt->execute();
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. Browser breakdown (US only)
    $stmt = $pdo->prepare("
        SELECT browser, COUNT(*) as count FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing' AND browser IS NOT NULL AND browser != ''
        GROUP BY browser ORDER BY count DESC LIMIT 10
    ");
    $stmt->execute();
    $browsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Hourly distribution (US only, for content analysis)
    $stmt = $pdo->prepare("
        SELECT
            HOUR(timestamp) as hour,
            COUNT(*) as visits,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as checkouts
        FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing'
        GROUP BY HOUR(timestamp)
        ORDER BY hour
    ");
    $stmt->execute();
    $hourly = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Top affiliate + page combos (which affiliate sends to which page)
    $stmt = $pdo->prepare("
        SELECT
            aff_id,
            SUBSTRING_INDEX(page_url, '?', 1) as page_url,
            COUNT(*) as visits,
            AVG(NULLIF(session_duration, 0)) as avg_duration,
            AVG(NULLIF(max_scroll_depth, 0)) as avg_scroll_depth,
            SUM(CASE WHEN buynow_clicks > 0 THEN 1 ELSE 0 END) as reached_checkout
        FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing' AND page_url IS NOT NULL AND page_url != ''
        GROUP BY aff_id, SUBSTRING_INDEX(page_url, '?', 1)
        ORDER BY visits DESC
        LIMIT 50
    ");
    $stmt->execute();
    $affPageCombos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 8. Fraud analysis per affiliate (US only)
    $stmt = $pdo->prepare("
        SELECT
            aff_id,
            COUNT(*) as total,
            SUM(CASE WHEN fraud_risk_level = 'high' THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN fraud_risk_level = 'risky' THEN 1 ELSE 0 END) as risky,
            SUM(CASE WHEN fraud_risk_level = 'suspicious' THEN 1 ELSE 0 END) as suspicious,
            SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots,
            AVG(NULLIF(fraud_score, 0)) as avg_fraud_score
        FROM affiliate_traffic
        WHERE $baseWhere AND page_type = 'landing'
        GROUP BY aff_id
        ORDER BY total DESC
        LIMIT 50
    ");
    $stmt->execute();
    $fraudByAffiliate = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'totals' => [
            'totalVisits' => (int)$totals['total_visits'],
            'uniqueAffiliates' => (int)$totals['unique_affiliates'],
            'uniqueVisitors' => (int)$totals['unique_visitors'],
            'shavedVisits' => (int)($totals['shaved_visits'] ?? 0),
            'avgDuration' => round((float)($totals['avg_duration'] ?? 0), 1),
            'avgScrollDepth' => round((float)($totals['avg_scroll_depth'] ?? 0), 1),
            'totalClicks' => (int)($totals['total_clicks'] ?? 0),
            'redirectClicks' => (int)($totals['total_redirect_clicks'] ?? 0),
            'buynowClicks' => (int)($totals['total_buynow_clicks'] ?? 0),
            'footerClicks' => (int)($totals['total_footer_clicks'] ?? 0),
            'videoPlays' => (int)($totals['total_video_plays'] ?? 0),
            'ctaBarClicks' => (int)($totals['total_cta_bar_clicks'] ?? 0),
            'vslClicks' => (int)($totals['total_vsl_clicks'] ?? 0),
            'reachedCheckout' => (int)($totals['reached_checkout'] ?? 0),
            'qualifiedVisits' => (int)($totals['qualified_visits'] ?? 0),
            'bounceCount' => (int)($totals['bounce_count'] ?? 0),
            'avgRedirectTimeMs' => round((float)($totals['avg_redirect_time_ms'] ?? 0)),
        ],
        'affiliates' => $affiliates,
        'pages' => $pages,
        'devices' => $devices,
        'browsers' => $browsers,
        'hourly' => $hourly,
        'affPageCombos' => $affPageCombos,
        'fraudByAffiliate' => $fraudByAffiliate
    ]);
}

// ================================================================
// REORDER CAMPAIGNS (daily recurring emails)
// ================================================================

function startReorderCampaign($pdo) {
    $data = getPostData();
    $domainId    = $data['domain_id'] ?? null;
    $orderId     = $data['order_id'] ?? '';
    $recipients  = $data['recipients'] ?? [];
    $maxSends    = intval($data['max_sends'] ?? 30);

    if (empty($orderId) || empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'Missing order_id or domain_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND domain_id = ? LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    $order = $stmt->fetch();
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $customerEmail = $order['customer_email'];
    if (empty($recipients)) $recipients = [$customerEmail];

    // Check if active campaign already exists for this order
    $stmt = $pdo->prepare("SELECT id FROM reorder_campaigns WHERE order_id = ? AND domain_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$orderId, $domainId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'An active campaign already exists for this order']);
        return;
    }

    $coupon = [];
    if (!empty($data['coupon_code'])) {
        $coupon = [
            'code'   => $data['coupon_code'],
            'type'   => $data['coupon_type'] ?? 'percentage',
            'amount' => $data['coupon_amount'] ?? 0
        ];
    }

    // First send is NOW (immediate), then daily at same time
    require_once __DIR__ . '/mailer.php';
    $result = sendReorderMail($order, $recipients, $coupon);

    if (!$result['success']) {
        echo json_encode(['success' => false, 'error' => 'First email failed: ' . ($result['error'] ?? 'Unknown')]);
        return;
    }

    // Schedule next send for tomorrow same time
    $nextSend = date('Y-m-d H:i:s', strtotime('+1 day'));

    $stmt = $pdo->prepare("INSERT INTO reorder_campaigns
        (domain_id, order_id, customer_email, recipients, coupon_code, coupon_type, coupon_amount, sends_count, max_sends, next_send_at, last_sent_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())");
    $stmt->execute([
        $domainId, $orderId, $customerEmail, json_encode($recipients),
        $coupon['code'] ?? null, $coupon['type'] ?? null,
        !empty($coupon['amount']) ? $coupon['amount'] : null,
        $maxSends, $nextSend
    ]);

    // Mark order as reorder mail sent
    $stmt = $pdo->prepare("UPDATE orders SET reorder_mail_sent = 1 WHERE order_id = ? AND domain_id = ?");
    $stmt->execute([$orderId, $domainId]);

    // Log the first send
    try {
        $stmt = $pdo->prepare("INSERT INTO reorder_mail_log (domain_id, order_id, recipients, subject, send_type, campaign_day, coupon_code, coupon_type, coupon_amount, sent_at)
            VALUES (?, ?, ?, 'Your MetaTrim BHB Supply Update', 'campaign', 1, ?, ?, ?, NOW())");
        $stmt->execute([$domainId, $orderId, implode(', ', $recipients),
            $coupon['code'] ?? null, $coupon['type'] ?? null,
            !empty($coupon['amount']) ? $coupon['amount'] : null]);
    } catch (PDOException $e) {
        // Fallback if subject/send_type/campaign_day columns don't exist yet
        $stmt = $pdo->prepare("INSERT INTO reorder_mail_log (domain_id, order_id, recipients, coupon_code, coupon_type, coupon_amount, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$domainId, $orderId, implode(', ', $recipients),
            $coupon['code'] ?? null, $coupon['type'] ?? null,
            !empty($coupon['amount']) ? $coupon['amount'] : null]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'First email sent & daily campaign started. Will send daily until customer reorders (max ' . $maxSends . ' days).'
    ]);
}

function stopReorderCampaign($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? null;
    $orderId  = $data['order_id'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'Missing order_id or domain_id']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE reorder_campaigns SET status = 'stopped', stopped_at = NOW() WHERE order_id = ? AND domain_id = ? AND status = 'active'");
    $stmt->execute([$orderId, $domainId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Campaign stopped']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No active campaign found']);
    }
}

function getReorderCampaign($pdo) {
    $data = getPostData();
    $domainId = $data['domain_id'] ?? $_GET['domain_id'] ?? null;
    $orderId  = $data['order_id'] ?? $_GET['order_id'] ?? '';

    if (empty($orderId) || empty($domainId)) {
        echo json_encode(['success' => false, 'error' => 'Missing order_id or domain_id']);
        return;
    }

    $campaign = null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM reorder_campaigns WHERE order_id = ? AND domain_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$orderId, $domainId]);
        $campaign = $stmt->fetch() ?: null;
    } catch (Exception $e) {
        // Table may not exist yet
    }

    // Get send history — try with new columns first, fall back to basic
    $logs = [];
    try {
        $logStmt = $pdo->prepare("SELECT subject, send_type, campaign_day, recipients, sent_at FROM reorder_mail_log WHERE order_id = ? AND domain_id = ? ORDER BY sent_at DESC LIMIT 50");
        $logStmt->execute([$orderId, $domainId]);
        $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        try {
            $logStmt = $pdo->prepare("SELECT recipients, sent_at FROM reorder_mail_log WHERE order_id = ? AND domain_id = ? ORDER BY sent_at DESC LIMIT 50");
            $logStmt->execute([$orderId, $domainId]);
            $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            // Table may not exist
        }
    }

    echo json_encode([
        'success' => true,
        'campaign' => $campaign ?: null,
        'sendHistory' => $logs
    ]);
}

// ============================================================
// CHECKOUT LINK CONFIGS
// ============================================================
function getCheckoutConfig($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $configType = $data['config_type'] ?? 'main';

    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id']);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, nickname, packages, config_type, updated_at FROM cb_checkout_configs WHERE domain_id = ? AND config_type = ?");
        $stmt->execute([$domainId, $configType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['packages'] = json_decode($row['packages'], true);
            echo json_encode(['success' => true, 'config' => $row]);
        } else {
            // Try to get nickname from domain's cb_vendor
            $dStmt = $pdo->prepare("SELECT cb_vendor FROM domains WHERE id = ?");
            $dStmt->execute([$domainId]);
            $domain = $dStmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'config' => null, 'cb_vendor' => $domain['cb_vendor'] ?? '']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function saveCheckoutConfig($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $configType = $data['config_type'] ?? 'main';
    $nickname = trim($data['nickname'] ?? '');
    $packages = $data['packages'] ?? [];

    if (!$domainId || !$nickname) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id or nickname']);
        return;
    }

    $packagesJson = is_string($packages) ? $packages : json_encode($packages);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO cb_checkout_configs (domain_id, config_type, nickname, packages)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nickname = VALUES(nickname), packages = VALUES(packages)
        ");
        $stmt->execute([$domainId, $configType, $nickname, $packagesJson]);

        echo json_encode(['success' => true, 'message' => 'Config saved']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getAllCheckoutConfigs($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    if (!$domainId) { echo json_encode(['success' => false, 'error' => 'Missing domain_id']); return; }

    try {
        $stmt = $pdo->prepare("SELECT id, nickname, packages, config_type, updated_at FROM cb_checkout_configs WHERE domain_id = ? ORDER BY FIELD(config_type, 'main') DESC, config_type ASC");
        $stmt->execute([$domainId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) { $r['packages'] = json_decode($r['packages'], true); }

        $dStmt = $pdo->prepare("SELECT cb_vendor FROM domains WHERE id = ?");
        $dStmt->execute([$domainId]);
        $domain = $dStmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'configs' => $rows, 'cb_vendor' => $domain['cb_vendor'] ?? '']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteCheckoutConfig($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $configType = $data['config_type'] ?? '';
    if (!$domainId || !$configType) { echo json_encode(['success' => false, 'error' => 'Missing params']); return; }

    try {
        $stmt = $pdo->prepare("DELETE FROM cb_checkout_configs WHERE domain_id = ? AND config_type = ?");
        $stmt->execute([$domainId, $configType]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getOrdersDateFilter($period) {
    $tz = new DateTimeZone('Asia/Karachi');
    $now = new DateTime('now', $tz);
    switch ($period) {
        case 'today':
            $start = clone $now; $start->setTime(0, 0, 0);
            $end   = clone $now; $end->setTime(23, 59, 59);
            break;
        case 'yesterday':
            $start = clone $now; $start->modify('-1 day')->setTime(0, 0, 0);
            $end   = clone $now; $end->modify('-1 day')->setTime(23, 59, 59);
            break;
        case 'this_week': case 'thisweek':
            $d = $now->format('N');
            $start = clone $now; $start->modify('-'.($d-1).' days')->setTime(0,0,0);
            $end   = clone $start; $end->modify('+6 days')->setTime(23,59,59);
            break;
        case 'last_week': case 'lastweek':
            $d = $now->format('N');
            $start = clone $now; $start->modify('-'.($d+6).' days')->setTime(0,0,0);
            $end   = clone $start; $end->modify('+6 days')->setTime(23,59,59);
            break;
        case 'this_month': case 'thismonth':
            $start = clone $now; $start->modify('first day of this month')->setTime(0,0,0);
            $end   = clone $now; $end->modify('last day of this month')->setTime(23,59,59);
            break;
        case 'all': default:
            return ['filter' => '1=1', 'params' => [], 'start' => null, 'end' => null];
    }
    return [
        'filter' => "date_created >= ? AND date_created <= ?",
        'params' => [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
        'start'  => $start->format('Y-m-d'),
        'end'    => $end->format('Y-m-d'),
    ];
}

function getSalesAnalytics($pdo) {
    $data     = getPostData();
    $domainId = intval($data['domain_id'] ?? 0);
    $period   = $data['period'] ?? 'this_month';

    $tf     = getOrdersDateFilter($period);
    $where  = ['domain_id = ?', 'is_test = 0'];
    $params = [$domainId];
    if ($tf['filter'] !== '1=1') { $where[] = $tf['filter']; $params = array_merge($params, $tf['params']); }

    $w = implode(' AND ', $where);

    // Active orders (exclude refunded/chargeback)
    $wActive = $w . " AND status NOT IN ('Refunded','Chargeback','RFND','CGBK') AND total_amount >= 0";

    // Summary stats — active orders only
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as revenue, COALESCE(SUM(vendor_net),0) as vendor_net FROM orders WHERE $wActive");
    $stmt->execute($params);
    $stats = $stmt->fetch();

    // Refund stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(ABS(total_amount)),0) as refund_total FROM orders WHERE $w AND (status IN ('Refunded','Chargeback','RFND','CGBK') OR total_amount < 0)");
    $stmt->execute($params);
    $refundStats = $stmt->fetch();

    // Revenue by day (chart) — all orders for visibility
    $stmt = $pdo->prepare("SELECT DATE(date_created) as day, COALESCE(SUM(CASE WHEN status NOT IN ('Refunded','Chargeback') THEN total_amount ELSE 0 END),0) as revenue, SUM(CASE WHEN status NOT IN ('Refunded','Chargeback') THEN 1 ELSE 0 END) as orders FROM orders WHERE $w GROUP BY DATE(date_created) ORDER BY day ASC");
    $stmt->execute($params);
    $days = $stmt->fetchAll();

    // Top products — active orders only
    $stmt = $pdo->prepare("SELECT product_names, COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as revenue FROM orders WHERE $wActive AND product_names IS NOT NULL AND product_names != '' GROUP BY product_names ORDER BY revenue DESC LIMIT 10");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Top countries — active orders only
    $stmt = $pdo->prepare("SELECT country, COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as revenue FROM orders WHERE $wActive AND country IS NOT NULL AND country != '' AND country != '-' GROUP BY country ORDER BY revenue DESC LIMIT 10");
    $stmt->execute($params);
    $countries = $stmt->fetchAll();

    $cnt     = intval($stats['cnt']);
    $revenue = floatval($stats['revenue']);
    $vendorNet = floatval($stats['vendor_net']);
    $avg     = $cnt > 0 ? round($revenue / $cnt, 2) : 0;

    echo json_encode([
        'success'    => true,
        'stats'      => [
            'orders' => $cnt, 'revenue' => $revenue, 'vendor_net' => $vendorNet, 'avg_order' => $avg,
            'refunds' => intval($refundStats['cnt']), 'refund_total' => floatval($refundStats['refund_total'])
        ],
        'chartDays'  => array_map(fn($r) => ['day' => $r['day'], 'revenue' => floatval($r['revenue']), 'orders' => intval($r['orders'])], $days),
        'products'   => array_map(fn($r) => ['name' => $r['product_names'], 'cnt' => intval($r['cnt']), 'revenue' => floatval($r['revenue'])], $products),
        'countries'  => array_map(fn($r) => ['country' => $r['country'], 'cnt' => intval($r['cnt']), 'revenue' => floatval($r['revenue'])], $countries),
    ]);
}

function getAffiliateRoi($pdo) {
    $data     = getPostData();
    $domainId = intval($data['domain_id'] ?? 0);
    $period   = $data['period'] ?? 'this_month';

    $tf     = getOrdersDateFilter($period);
    $where  = ['domain_id = ?', 'is_test = 0'];
    $params = [$domainId];
    if ($tf['filter'] !== '1=1') { $where[] = $tf['filter']; $params = array_merge($params, $tf['params']); }

    $w = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT
            affiliate_id,
            MAX(affiliate_name) as affiliate_name,
            COUNT(*) as order_count,
            COALESCE(SUM(total_amount),0) as total_revenue,
            COALESCE(SUM(vendor_net),0) as total_vendor_net,
            COALESCE(AVG(total_amount),0) as avg_order_value,
            COALESCE(AVG(vendor_net),0) as avg_vendor_net
        FROM orders
        WHERE $w AND affiliate_id IS NOT NULL AND affiliate_id != '' AND affiliate_id != '-'
        GROUP BY affiliate_id
        ORDER BY total_revenue DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $affiliates = array_map(fn($r) => [
        'aff_id'          => $r['affiliate_id'],
        'aff_name'        => $r['affiliate_name'] ?? '',
        'order_count'     => intval($r['order_count']),
        'total_revenue'   => round(floatval($r['total_revenue']), 2),
        'total_vendor_net'=> round(floatval($r['total_vendor_net']), 2),
        'avg_order_value' => round(floatval($r['avg_order_value']), 2),
        'avg_vendor_net'  => round(floatval($r['avg_vendor_net']), 2),
    ], $rows);

    echo json_encode(['success' => true, 'affiliates' => $affiliates]);
}

function getIpnEvents($pdo) {
    $data  = getPostData();
    $type  = strtoupper(trim($data['event_type'] ?? ''));

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS cb_ipn_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        notification_type VARCHAR(20) DEFAULT NULL,
        receipt VARCHAR(100) DEFAULT NULL,
        vendor VARCHAR(100) DEFAULT NULL,
        affiliate VARCHAR(100) DEFAULT NULL,
        product VARCHAR(100) DEFAULT NULL,
        sku VARCHAR(100) DEFAULT NULL,
        amount DECIMAL(10,2) DEFAULT NULL,
        currency VARCHAR(10) DEFAULT NULL,
        tracking_codes VARCHAR(255) DEFAULT NULL,
        txn_type VARCHAR(50) DEFAULT NULL,
        customer_email VARCHAR(255) DEFAULT NULL,
        customer_name VARCHAR(255) DEFAULT NULL,
        customer_address1 VARCHAR(255) DEFAULT NULL,
        customer_city VARCHAR(100) DEFAULT NULL,
        customer_state VARCHAR(100) DEFAULT NULL,
        customer_zip VARCHAR(20) DEFAULT NULL,
        customer_country VARCHAR(10) DEFAULT NULL,
        verified TINYINT(1) DEFAULT 0,
        raw_post TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_receipt (receipt),
        INDEX idx_type (notification_type),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $where  = [];
    $params = [];
    if ($type) { $where[] = 'notification_type = ?'; $params[] = $type; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT * FROM cb_ipn_events $w ORDER BY created_at DESC LIMIT 500");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'rows' => $rows]);
}

function deleteCbPixelHit($pdo) {
    $data = getPostData();
    $id   = intval($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'id required']); return; }
    $stmt = $pdo->prepare("DELETE FROM cb_pixel_hits WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
}

function getCbPixelHits($pdo) {
    $data      = getPostData();
    $domainKey = trim($data['domain_key'] ?? '');
    $event     = trim($data['event'] ?? ''); // 'form', 'sale', or ''
    $dateFrom  = trim($data['date_from'] ?? '');
    $dateTo    = trim($data['date_to'] ?? '');

    // Ensure table exists even before first pixel hit
    $pdo->exec("CREATE TABLE IF NOT EXISTS cb_pixel_hits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_key VARCHAR(100) NOT NULL DEFAULT '',
        event_type VARCHAR(20) NOT NULL DEFAULT '',
        verification_hash VARCHAR(255) DEFAULT NULL,
        role VARCHAR(20) DEFAULT NULL,
        affiliate_id VARCHAR(100) DEFAULT NULL,
        vendor VARCHAR(100) DEFAULT NULL,
        tracking_codes VARCHAR(255) DEFAULT NULL,
        product_title VARCHAR(255) DEFAULT NULL,
        upsell_flow_id VARCHAR(100) DEFAULT NULL,
        upsell_original_receipt VARCHAR(100) DEFAULT NULL,
        item_no VARCHAR(50) DEFAULT NULL,
        order_id VARCHAR(100) DEFAULT NULL,
        customer_billing_info TEXT DEFAULT NULL,
        customer_shipping_info TEXT DEFAULT NULL,
        transaction_type VARCHAR(50) DEFAULT NULL,
        currency VARCHAR(10) DEFAULT NULL,
        total_product_amount DECIMAL(10,2) DEFAULT NULL,
        total_tax_amount DECIMAL(10,2) DEFAULT NULL,
        total_shipping_amount DECIMAL(10,2) DEFAULT NULL,
        order_language VARCHAR(20) DEFAULT NULL,
        payment_method VARCHAR(50) DEFAULT NULL,
        transaction_time VARCHAR(100) DEFAULT NULL,
        raw_params TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_domain_event (domain_key, event_type),
        INDEX idx_order_id (order_id),
        INDEX idx_affiliate (affiliate_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $where  = [];
    $params = [];

    if ($domainKey) { $where[] = 'domain_key = ?'; $params[] = $domainKey; }
    if ($event)     { $where[] = 'event_type = ?';  $params[] = $event; }
    if ($dateFrom)  { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
    if ($dateTo)    { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo; }

    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Summary stats
    $stmtStats = $pdo->prepare("SELECT
        COUNT(*) as total,
        SUM(CASE WHEN event_type='form' THEN 1 ELSE 0 END) as form_visits,
        SUM(CASE WHEN event_type='sale' THEN 1 ELSE 0 END) as sales,
        COALESCE(SUM(CASE WHEN event_type='sale' THEN total_product_amount ELSE 0 END), 0) as total_revenue
        FROM cb_pixel_hits $w");
    $stmtStats->execute($params);
    $stats = $stmtStats->fetch();

    // Rows
    $stmtRows = $pdo->prepare("SELECT * FROM cb_pixel_hits $w ORDER BY created_at DESC LIMIT 500");
    $stmtRows->execute($params);
    $rows = $stmtRows->fetchAll();

    $formVisits = intval($stats['form_visits']);
    $sales      = intval($stats['sales']);
    $convRate   = $formVisits > 0 ? round(($sales / $formVisits) * 100, 1) : 0;

    echo json_encode([
        'success' => true,
        'stats'   => [
            'form_visits' => $formVisits,
            'sales'       => $sales,
            'conv_rate'   => $convRate,
            'total_revenue' => round(floatval($stats['total_revenue']), 2),
        ],
        'rows' => array_map(fn($r) => [
            'id'                    => intval($r['id']),
            'event_type'            => $r['event_type'],
            'affiliate_id'          => $r['affiliate_id'] ?? '',
            'vendor'                => $r['vendor'] ?? '',
            'product_title'         => $r['product_title'] ?? '',
            'item_no'               => $r['item_no'] ?? '',
            'order_id'              => $r['order_id'] ?? '',
            'customer_billing_info' => $r['customer_billing_info'] ?? '',
            'customer_shipping_info'=> $r['customer_shipping_info'] ?? '',
            'transaction_type'      => $r['transaction_type'] ?? '',
            'currency'              => $r['currency'] ?? 'USD',
            'total_product_amount'  => $r['total_product_amount'] ? floatval($r['total_product_amount']) : null,
            'total_tax_amount'      => $r['total_tax_amount'] ? floatval($r['total_tax_amount']) : null,
            'total_shipping_amount' => $r['total_shipping_amount'] ? floatval($r['total_shipping_amount']) : null,
            'payment_method'        => $r['payment_method'] ?? '',
            'transaction_time'      => $r['transaction_time'] ?? '',
            'tracking_codes'        => $r['tracking_codes'] ?? '',
            'ip_address'            => $r['ip_address'] ?? '',
            'created_at'            => $r['created_at'],
        ], $rows),
    ]);
}

function autoRunIpqs($pdo, $trafficId) {
    try {
        $stmt = $pdo->prepare("SELECT ip_address, fraud_score, ipqs_raw FROM affiliate_traffic WHERE id = ?");
        $stmt->execute([$trafficId]);
        $row = $stmt->fetch();
        if (!$row || !$row['ip_address']) return;

        // Already has fraud score AND ipqs_raw — skip
        if ($row['fraud_score'] !== null && $row['ipqs_raw'] !== null) return;

        $ip = $row['ip_address'];

        // Check cache from other records with same IP
        $cacheStmt = $pdo->prepare("SELECT fraud_score, fraud_risk_level, fraud_flags, ipqs_raw FROM affiliate_traffic WHERE ip_address = ? AND fraud_score IS NOT NULL ORDER BY timestamp DESC LIMIT 1");
        $cacheStmt->execute([$ip]);
        $cached = $cacheStmt->fetch();

        if ($cached) {
            $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = COALESCE(fraud_score, ?), fraud_risk_level = COALESCE(fraud_risk_level, ?), fraud_flags = COALESCE(fraud_flags, ?), ipqs_raw = COALESCE(ipqs_raw, ?) WHERE id = ?");
            $upd->execute([$cached['fraud_score'], $cached['fraud_risk_level'], $cached['fraud_flags'], $cached['ipqs_raw'], $trafficId]);
            return;
        }

        // No cache — call IPQS API if allowed
        if (!canCallIPQS($pdo)) return;

        require_once __DIR__ . '/ipqs.php';
        $ipqs = new IPQS(IPQS_API_KEYS);
        $result = $ipqs->analyzeIP($ip);
        if (!$result) return;

        $storageData = $ipqs->getStorageData($result);
        $upd = $pdo->prepare("UPDATE affiliate_traffic SET fraud_score = ?, fraud_risk_level = ?, fraud_flags = ?, ipqs_raw = ? WHERE id = ?");
        $upd->execute([$storageData['fraud_score'], $storageData['fraud_risk_level'], $storageData['fraud_flags'], json_encode($result), $trafficId]);
        incrementIPQSCounter($pdo);
    } catch (\Exception $e) {
        // Silent fail — don't block order matching
    }
}

function logCopyAttempt($pdo) {
    $data = getPostData();

    $pdo->exec("CREATE TABLE IF NOT EXISTS copy_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_id INT DEFAULT NULL,
        expected_host VARCHAR(255) DEFAULT NULL,
        actual_host VARCHAR(255) DEFAULT NULL,
        actual_url TEXT DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        referrer TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at),
        INDEX idx_actual_host (actual_host)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]
        : ($_SERVER['REMOTE_ADDR'] ?? '');

    $stmt = $pdo->prepare("INSERT INTO copy_attempts (domain_id, expected_host, actual_host, actual_url, user_agent, referrer, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        intval($data['domain_id'] ?? 0),
        substr($data['expected_host'] ?? '', 0, 255),
        substr($data['actual_host'] ?? '', 0, 255),
        substr($data['actual_url'] ?? '', 0, 2000),
        substr($data['user_agent'] ?? '', 0, 500),
        substr($data['referrer'] ?? '', 0, 500),
        substr(trim($ip), 0, 45)
    ]);

    echo json_encode(['success' => true]);
}

function exportTraffic($pdo) {
    $data = getPostData();
    $domainId = intval($data['domain_id'] ?? 0);
    $period = $data['period'] ?? 'all';

    if (!$domainId) {
        echo json_encode(['error' => 'domain_id required']);
        return;
    }

    $where = "domain_id = ? AND page_type = 'landing'";
    $params = [$domainId];

    if ($period !== 'all' && !empty($period)) {
        $tf = getTimeFilter($period);
        $where .= " AND $tf";
    }

    // Get all traffic entries (no limit)
    $stmt = $pdo->prepare("
        SELECT
            t.id, t.aff_id, t.sub_id, t.page_url, t.referrer, t.browser, t.device,
            t.ip_address, t.country, t.country_code, t.was_shaved, t.reached_checkout,
            t.timestamp, t.session_duration, t.max_scroll_depth, t.total_clicks,
            t.redirect_clicks, t.buynow_clicks, t.footer_clicks,
            t.video_plays, t.cta_bar_clicks, t.vsl_clicks, t.bounce,
            t.fraud_score, t.fraud_risk_level, t.is_bot, t.is_iframe,
            t.has_adblock, t.js_error_count, t.page_type, t.sessid2,
            t.matched_order_id
        FROM affiliate_traffic t
        WHERE $where
        ORDER BY t.timestamp DESC
        LIMIT 50000
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Get orders for this domain to enrich with order data
    $orderStmt = $pdo->prepare("SELECT order_id, customer_name, customer_email, total_amount, vendor_net, product_names, status FROM orders WHERE domain_id = ?");
    $orderStmt->execute([$domainId]);
    $ordersMap = [];
    foreach ($orderStmt->fetchAll() as $o) {
        $ordersMap[$o['order_id']] = $o;
    }

    // Get upsell data per sessid2
    $sessids = array_filter(array_unique(array_column($rows, 'sessid2')));
    $upsellMap = [];
    if (!empty($sessids)) {
        $ph = implode(',', array_fill(0, count($sessids), '?'));
        $uStmt = $pdo->prepare("SELECT sessid2, COUNT(*) as upsell_count FROM affiliate_traffic WHERE sessid2 IN ($ph) AND page_type = 'upsell' AND domain_id = ? GROUP BY sessid2");
        $uStmt->execute(array_merge($sessids, [$domainId]));
        foreach ($uStmt->fetchAll() as $u) {
            $upsellMap[$u['sessid2']] = intval($u['upsell_count']);
        }
    }

    // Build export data
    $export = [];
    foreach ($rows as $r) {
        $orderId = $r['matched_order_id'] ?? '';
        $order = $orderId && isset($ordersMap[$orderId]) ? $ordersMap[$orderId] : null;
        $upsellCount = ($r['sessid2'] && isset($upsellMap[$r['sessid2']])) ? $upsellMap[$r['sessid2']] : 0;

        // Extract landing page path
        $pagePath = '';
        if (!empty($r['page_url'])) {
            $parsed = parse_url($r['page_url']);
            $pagePath = $parsed['path'] ?? $r['page_url'];
        }

        $export[] = [
            'date'              => $r['timestamp'],
            'affiliate_id'      => $r['aff_id'] ?? '',
            'sub_id'            => $r['sub_id'] ?? '',
            'landing_page'      => $pagePath,
            'country'           => $r['country'] ?? '',
            'country_code'      => $r['country_code'] ?? '',
            'ip_address'        => $r['ip_address'] ?? '',
            'device'            => $r['device'] ?? '',
            'browser'           => $r['browser'] ?? '',
            'scroll_depth'      => $r['max_scroll_depth'] ?? 0,
            'session_duration'  => $r['session_duration'] ?? 0,
            'total_clicks'      => $r['total_clicks'] ?? 0,
            'redirect_clicks'   => $r['redirect_clicks'] ?? 0,
            'buynow_clicks'     => $r['buynow_clicks'] ?? 0,
            'footer_clicks'     => $r['footer_clicks'] ?? 0,
            'video_plays'       => $r['video_plays'] ?? 0,
            'cta_bar_clicks'    => $r['cta_bar_clicks'] ?? 0,
            'vsl_clicks'        => $r['vsl_clicks'] ?? 0,
            'reached_checkout'  => $r['reached_checkout'] ? 'Yes' : 'No',
            'was_shaved'        => $r['was_shaved'] ? 'Yes' : 'No',
            'is_bounce'         => $r['bounce'] ? 'Yes' : 'No',
            'fraud_score'       => $r['fraud_score'] ?? '',
            'fraud_risk'        => $r['fraud_risk_level'] ?? '',
            'is_bot'            => $r['is_bot'] ? 'Yes' : 'No',
            'is_iframe'         => $r['is_iframe'] ? 'Yes' : 'No',
            'has_adblock'       => $r['has_adblock'] ? 'Yes' : 'No',
            'js_errors'         => $r['js_error_count'] ?? 0,
            'upsell_pages'      => $upsellCount,
            'matched_order_id'  => $orderId,
            'order_status'      => $order ? $order['status'] : '',
            'order_amount'      => $order ? $order['total_amount'] : '',
            'vendor_net'        => $order ? $order['vendor_net'] : '',
            'product'           => $order ? $order['product_names'] : '',
            'customer_name'     => $order ? $order['customer_name'] : '',
            'customer_email'    => $order ? $order['customer_email'] : '',
            'referrer'          => $r['referrer'] ?? '',
        ];
    }

    echo json_encode(['success' => true, 'data' => $export, 'total' => count($export)]);
}

// ─── Tracked Sources ───────────────────────────────────────────
function addTrackedSource($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $sourceUrl = trim($data['source_url'] ?? '');
    $label = trim($data['label'] ?? '');

    if (!$domainId || !$sourceUrl) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id or source_url']);
        return;
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO tracked_sources (domain_id, source_url, label) VALUES (?, ?, ?)");
    $stmt->execute([$domainId, $sourceUrl, $label ?: null]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
}

function removeTrackedSource($pdo) {
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); return; }

    $pdo->prepare("DELETE FROM tracked_sources WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function getTrackedSources($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? $_GET['domain_id'] ?? 0);
    $period = $data['period'] ?? $_GET['period'] ?? 'today';

    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id']);
        return;
    }

    // Get all tracked sources for this domain
    $stmt = $pdo->prepare("SELECT id, source_url, label, created_at FROM tracked_sources WHERE domain_id = ? ORDER BY created_at ASC");
    $stmt->execute([$domainId]);
    $tracked = $stmt->fetchAll();

    if (empty($tracked)) {
        echo json_encode(['success' => true, 'sources' => []]);
        return;
    }

    // Get traffic counts for each tracked source in the selected period
    $timeFilter = getTimeFilter($period);
    $domainFilter = " AND domain_id = $domainId";

    $result = [];
    foreach ($tracked as $t) {
        $src = $t['source_url'];
        // Match referrer against the tracked source
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as visits
            FROM affiliate_traffic
            WHERE $timeFilter $domainFilter
            AND (
                referrer LIKE ? OR
                SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '?', 1) = ?
            )
        ");
        $likeSrc = '%' . $src . '%';
        $stmt->execute([$likeSrc, $src]);
        $visits = (int)$stmt->fetchColumn();

        // Find top affiliate for this source (all time for this domain)
        $stmtAff = $pdo->prepare("
            SELECT t.aff_id, COALESCE(n.name, '') as aff_name, COUNT(*) as cnt
            FROM affiliate_traffic t
            LEFT JOIN affiliate_names n ON n.domain_id = t.domain_id AND n.aff_id = t.aff_id
            WHERE t.domain_id = ? AND t.aff_id IS NOT NULL AND t.aff_id != ''
            AND (
                t.referrer LIKE ? OR
                SUBSTRING_INDEX(REPLACE(REPLACE(t.referrer, 'https://', ''), 'http://', ''), '?', 1) = ?
            )
            GROUP BY t.aff_id, n.name ORDER BY cnt DESC LIMIT 1
        ");
        $stmtAff->execute([$domainId, $likeSrc, $src]);
        $topAff = $stmtAff->fetch();

        // Top landing page for this source
        $stmtPg = $pdo->prepare("
            SELECT CONCAT('/', SUBSTRING_INDEX(
                SUBSTRING(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''),
                    LENGTH(SUBSTRING_INDEX(REPLACE(REPLACE(page_url, 'https://', ''), 'http://', ''), '/', 1)) + 2
                ), '?', 1
            )) as page_path, COUNT(*) as cnt
            FROM affiliate_traffic
            WHERE $timeFilter $domainFilter AND page_url IS NOT NULL AND page_url != ''
            AND (referrer LIKE ? OR SUBSTRING_INDEX(REPLACE(REPLACE(referrer, 'https://', ''), 'http://', ''), '?', 1) = ?)
            GROUP BY page_path ORDER BY cnt DESC LIMIT 1
        ");
        $stmtPg->execute([$likeSrc, $src]);
        $topPg = $stmtPg->fetch();

        $result[] = [
            'id' => (int)$t['id'],
            'source_url' => $t['source_url'],
            'label' => $t['label'],
            'visits' => $visits,
            'aff_id' => $topAff ? $topAff['aff_id'] : null,
            'aff_name' => $topAff ? $topAff['aff_name'] : null,
            'landing_page' => $topPg ? $topPg['page_path'] : null,
            'created_at' => $t['created_at']
        ];
    }

    echo json_encode(['success' => true, 'sources' => $result]);
}

// ================================================================
// VSL SHUFFLE
// ================================================================

function getVslConfigs($pdo) {
    $stmt = $pdo->query("
        SELECT v.id, v.domain_id, v.page_url, v.page_title, v.has_video, v.video_source, v.embed_type, v.embed_code, v.vidalytics_email, v.confirmed, v.sort_order, d.label, d.platform
        FROM vsl_shuffle v
        JOIN domains d ON d.id = v.domain_id
        ORDER BY v.domain_id, v.sort_order
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by domain
    $grouped = [];
    foreach ($rows as $r) {
        $did = $r['domain_id'];
        if (!isset($grouped[$did])) {
            $grouped[$did] = [
                'domainId' => $did,
                'label' => $r['label'],
                'platform' => $r['platform'] ?: 'buygoods',
                'pages' => []
            ];
        }
        $grouped[$did]['pages'][] = [
            'id' => (int)$r['id'],
            'url' => $r['page_url'],
            'title' => $r['page_title'] ?: '',
            'has_video' => (int)$r['has_video'],
            'video_source' => $r['video_source'] ?: '',
            'embed_type' => $r['embed_type'] ?: '',
            'embed_code' => $r['embed_code'] ?: '',
            'vidalytics_email' => $r['vidalytics_email'] ?: '',
            'confirmed' => (int)$r['confirmed']
        ];
    }

    echo json_encode(['success' => true, 'configs' => array_values($grouped)]);
}

function saveVslConfig($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $pages = $data['pages'] ?? [];

    if (!$domainId || empty($pages) || !is_array($pages)) {
        echo json_encode(['success' => false, 'error' => 'domain_id and pages[] required']);
        return;
    }

    // Remove existing pages for this domain, then re-insert
    $pdo->prepare("DELETE FROM vsl_shuffle WHERE domain_id = ?")->execute([$domainId]);

    $stmt = $pdo->prepare("INSERT INTO vsl_shuffle (domain_id, page_url, sort_order) VALUES (?, ?, ?)");
    foreach ($pages as $i => $url) {
        $stmt->execute([$domainId, trim($url), $i]);
    }

    echo json_encode(['success' => true]);
}

function deleteVslConfig($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);

    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'domain_id required']);
        return;
    }

    $pdo->prepare("DELETE FROM vsl_shuffle WHERE domain_id = ?")->execute([$domainId]);
    echo json_encode(['success' => true]);
}

function updateVslPage($pdo) {
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'id required']);
        return;
    }

    $fields = [];
    $params = [];

    if (isset($data['page_title'])) {
        $fields[] = 'page_title = ?';
        $params[] = $data['page_title'];
    }
    if (isset($data['has_video'])) {
        $fields[] = 'has_video = ?';
        $params[] = (int)$data['has_video'];
    }
    if (isset($data['video_source'])) {
        $fields[] = 'video_source = ?';
        $params[] = $data['video_source'];
    }
    if (isset($data['embed_type'])) {
        $fields[] = 'embed_type = ?';
        $params[] = $data['embed_type'];
    }
    if (isset($data['embed_code'])) {
        $fields[] = 'embed_code = ?';
        $params[] = $data['embed_code'];
    }
    if (isset($data['confirmed'])) {
        $fields[] = 'confirmed = ?';
        $params[] = (int)$data['confirmed'];
    }
    if (isset($data['vidalytics_email'])) {
        $fields[] = 'vidalytics_email = ?';
        $params[] = $data['vidalytics_email'];
    }

    if (empty($fields)) {
        echo json_encode(['success' => false, 'error' => 'No fields to update']);
        return;
    }

    $params[] = $id;
    $pdo->prepare("UPDATE vsl_shuffle SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    echo json_encode(['success' => true]);
}

function getVslEmbed($pdo) {
    // Public endpoint — returns embed code for a given page ID
    // Can be called via GET (?action=get_vsl_embed&id=X) or POST
    $data = getPostData();
    $id = (int)($data['id'] ?? $_GET['id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'id required']);
        return;
    }

    $stmt = $pdo->prepare("SELECT embed_code FROM vsl_shuffle WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['embed_code'])) {
        echo json_encode(['success' => false, 'error' => 'No embed code found']);
        return;
    }

    echo json_encode(['success' => true, 'embed_code' => $row['embed_code']]);
}

// ================================================================
// PER-DOMAIN SHIPPING CONFIG
// Powers the country selector + dynamic shipping override on opted-in
// pages via the t.js tracker. Only domains with shipping_enabled=1
// receive the shipping payload.
// ================================================================

function getShippingConfig($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? $_GET['domain_id'] ?? 0);
    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT shipping_enabled, card_config FROM domains WHERE id = ? LIMIT 1");
    $stmt->execute([$domainId]);
    $domain = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$domain) {
        echo json_encode(['success' => false, 'error' => 'Domain not found']);
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT id, country_code, country_name,
                ship_1_bottle, ship_2_bottle, ship_3_bottle, ship_6_bottle
         FROM domain_shipping_config
         WHERE domain_id = ?
         ORDER BY country_name ASC"
    );
    $stmt->execute([$domainId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cardConfig = null;
    if (!empty($domain['card_config'])) {
        $cardConfig = json_decode($domain['card_config'], true);
    }

    echo json_encode([
        'success' => true,
        'shipping_enabled' => (int)$domain['shipping_enabled'],
        'card_config' => $cardConfig,
        'rows' => $rows
    ]);
}

function saveShippingRow($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $countryCode = strtoupper(trim($data['country_code'] ?? ''));
    $countryName = trim($data['country_name'] ?? '');
    $s1 = (float)($data['ship_1_bottle'] ?? 0);
    $s2 = (float)($data['ship_2_bottle'] ?? 0);
    $s3 = (float)($data['ship_3_bottle'] ?? 0);
    $s6 = (float)($data['ship_6_bottle'] ?? 0);

    if (!$domainId || strlen($countryCode) !== 2 || $countryName === '') {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid fields']);
        return;
    }
    foreach ([$s1, $s2, $s3, $s6] as $v) {
        if ($v < 0 || $v > 99999.99) {
            echo json_encode(['success' => false, 'error' => 'Shipping value out of range']);
            return;
        }
    }

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            // Update existing row (scoped to this domain)
            $stmt = $pdo->prepare(
                "UPDATE domain_shipping_config
                 SET country_code = ?, country_name = ?,
                     ship_1_bottle = ?, ship_2_bottle = ?,
                     ship_3_bottle = ?, ship_6_bottle = ?
                 WHERE id = ? AND domain_id = ?"
            );
            $stmt->execute([$countryCode, $countryName, $s1, $s2, $s3, $s6, $id, $domainId]);
            $rowId = $id;
        } else {
            // Upsert by (domain_id, country_code)
            $stmt = $pdo->prepare(
                "INSERT INTO domain_shipping_config
                    (domain_id, country_code, country_name,
                     ship_1_bottle, ship_2_bottle, ship_3_bottle, ship_6_bottle)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    country_name = VALUES(country_name),
                    ship_1_bottle = VALUES(ship_1_bottle),
                    ship_2_bottle = VALUES(ship_2_bottle),
                    ship_3_bottle = VALUES(ship_3_bottle),
                    ship_6_bottle = VALUES(ship_6_bottle)"
            );
            $stmt->execute([$domainId, $countryCode, $countryName, $s1, $s2, $s3, $s6]);
            $rowId = (int)$pdo->lastInsertId();
            if ($rowId === 0) {
                // Existing row updated via ON DUPLICATE KEY — fetch its id
                $q = $pdo->prepare("SELECT id FROM domain_shipping_config WHERE domain_id = ? AND country_code = ?");
                $q->execute([$domainId, $countryCode]);
                $rowId = (int)$q->fetchColumn();
            }
        }

        // Flip shipping_enabled = 1 on first row insert
        $pdo->prepare("UPDATE domains SET shipping_enabled = 1 WHERE id = ?")->execute([$domainId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $rowId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function deleteShippingRow($pdo) {
    $data = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $id = (int)($data['id'] ?? 0);
    if (!$domainId || !$id) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id or id']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("DELETE FROM domain_shipping_config WHERE id = ? AND domain_id = ?");
        $stmt->execute([$id, $domainId]);

        // If no rows remain for this domain, flip shipping_enabled back to 0
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM domain_shipping_config WHERE domain_id = ?");
        $countStmt->execute([$domainId]);
        $remaining = (int)$countStmt->fetchColumn();
        if ($remaining === 0) {
            $pdo->prepare("UPDATE domains SET shipping_enabled = 0 WHERE id = ?")->execute([$domainId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'remaining' => $remaining]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// ================================================================
// STANDALONE SHIPPING DOMAIN REGISTRATION
// Register a domain for shipping-only use (no BuyGoods/DS24/CB script needed).
// The admin pastes the s.js CDN script on their page instead of t.js.
// ================================================================

function registerShippingDomain($pdo) {
    $data = getPostData();
    $label = trim($data['label'] ?? '');
    $domainUrl = trim($data['domain_url'] ?? '');

    if (empty($label) || empty($domainUrl)) {
        echo json_encode(['success' => false, 'error' => 'Label and domain URL are required']);
        return;
    }

    $domainKey = generateDomainKey($domainUrl);

    // Check for duplicate domain_key
    $chk = $pdo->prepare("SELECT id FROM domains WHERE domain_key = ?");
    $chk->execute([$domainKey]);
    if ($chk->fetch()) {
        echo json_encode(['success' => false, 'error' => 'A domain with this URL is already registered (key: ' . $domainKey . ')']);
        return;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO domains (domain_key, domain_url, label, bg_tracking_script, platform, shipping_enabled, status)
         VALUES (?, ?, ?, NULL, 'buygoods', 1, 'active')"
    );
    $stmt->execute([$domainKey, $domainUrl, $label]);

    echo json_encode([
        'success' => true,
        'domain_id' => (int)$pdo->lastInsertId(),
        'domain_key' => $domainKey
    ]);
}

function getShippingDomains($pdo) {
    $stmt = $pdo->prepare(
        "SELECT id, domain_key, domain_url, label, shipping_enabled, status, created_at
         FROM domains
         WHERE (bg_tracking_script IS NULL OR bg_tracking_script = '')
           AND shipping_enabled = 1
           AND status = 'active'
         ORDER BY created_at DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'domains' => $rows]);
}

function deleteShippingDomain($pdo) {
    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'Missing domain id']);
        return;
    }

    // Only allow deleting shipping-only domains (no BG script)
    $stmt = $pdo->prepare(
        "DELETE FROM domains WHERE id = ? AND (bg_tracking_script IS NULL OR bg_tracking_script = '')"
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Domain not found or is a full shaver domain (delete via Setup instead)']);
    }
}

// ================================================================
// AUTO-DETECTED CARD CONFIG
// Called by the shipping bootstrap (s.js / t.js) on first page load
// to report which pricing cards exist on the page and their labels.
// ================================================================

function saveCardConfig($pdo) {
    $data = getPostData();
    $domainKey = trim($data['domain_key'] ?? '');
    $cards = $data['cards'] ?? [];

    if (empty($domainKey) || !is_array($cards) || empty($cards)) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_key or cards']);
        return;
    }

    // Validate card structure: each must have slot + label
    $clean = [];
    foreach ($cards as $c) {
        $slot = trim($c['slot'] ?? '');
        $label = trim($c['label'] ?? '');
        if ($slot !== '' && $label !== '') {
            $clean[] = ['slot' => $slot, 'label' => $label];
        }
    }
    if (empty($clean)) {
        echo json_encode(['success' => false, 'error' => 'No valid cards']);
        return;
    }

    $stmt = $pdo->prepare("UPDATE domains SET card_config = ? WHERE domain_key = ? AND status = 'active'");
    $stmt->execute([json_encode($clean), $domainKey]);

    echo json_encode(['success' => true, 'cards' => $clean]);
}

// ================================================================
// URL-PARAM ORDER CAPTURE
// Auto-grabs order details from BG upsell page URLs the moment a buyer
// lands on upsell1.html / upsell2.html with order_id in the params.
// Avoids waiting for CSV upload — orders appear in dashboard in real time.
// ================================================================

/**
 * Parse a BG upsell URL and return the LATEST order block.
 *
 * BG packs all customer + order data into URL params (creditcards_name,
 * emailaddress, item, order_id, total, etc.). When a buyer accepts an
 * upsell, the next page's URL contains BOTH the original purchase block
 * AND the new upsell purchase block, separated. Each subsequent block
 * is URL-encoded one additional level (so values stay valid as nested
 * encoded strings).
 *
 * Strategy:
 *   1. Split query string by `&creditcards_name=` — that marker reliably
 *      starts every order block (BG always emits it as the first field).
 *   2. The LAST block is the most recent purchase.
 *   3. urldecode each value enough times that subsequent decodes don't
 *      change it (handles 1×, 2×, 3× nested encoding without hard-coding
 *      a level — and stops on stable values to avoid corrupting strings
 *      that happened to contain literal `+` or `%`).
 *
 * Returns associative array of decoded fields, or null if no order data.
 */
function bgParseLatestOrderBlock(string $urlString): ?array {
    $query = parse_url($urlString, PHP_URL_QUERY);
    if (!$query) return null;

    // Strip leading & or ? if present
    $query = ltrim($query, '?&');

    // Split into blocks - each block starts with creditcards_name
    $blocks  = [];
    $current = [];
    foreach (explode('&', $query) as $pair) {
        if ($pair === '') continue;
        $eq = strpos($pair, '=');
        $key = $eq === false ? $pair : substr($pair, 0, $eq);
        $val = $eq === false ? '' : substr($pair, $eq + 1);

        if ($key === 'creditcards_name' && !empty($current)) {
            $blocks[] = $current;
            $current = [];
        }
        $current[$key] = $val;
    }
    if (!empty($current)) $blocks[] = $current;
    if (empty($blocks)) return null;

    // Latest block is at the end
    $block = end($blocks);

    // Decode each value until stable (or 5 max iterations to avoid infinite loop)
    $decoded = [];
    foreach ($block as $key => $val) {
        for ($i = 0; $i < 5; $i++) {
            $next = urldecode($val);
            if ($next === $val) break;
            $val = $next;
        }
        $decoded[$key] = $val;
    }
    return $decoded;
}

function captureUrlOrder($pdo): void {
    $data     = getPostData();
    $domainId = (int)($data['domain_id'] ?? 0);
    $pageUrl  = trim($data['page_url'] ?? '');
    $sessid2  = trim($data['sessid2'] ?? '');
    $affIdJs  = trim($data['aff_id'] ?? '');
    $subIdJs  = trim($data['sub_id'] ?? '');
    $referrer = trim($data['referrer'] ?? '');

    if (!$domainId || !$pageUrl) {
        echo json_encode(['success' => false, 'error' => 'Missing domain_id or page_url']);
        return;
    }

    $block = bgParseLatestOrderBlock($pageUrl);
    if (!$block || empty($block['order_id'])) {
        echo json_encode(['success' => false, 'error' => 'No order_id found in URL']);
        return;
    }

    $orderId       = trim((string)$block['order_id']);
    $orderIdGlobal = trim((string)($block['order_id_global'] ?? ''));

    // Decide upsell level by inspecting BOTH order_id occurrences in the URL
    // (count gives us how many purchases this customer has made by this page).
    // Block index 0 = original sale, 1 = upsell1 sale, 2 = upsell2 sale, etc.
    $occurrences = substr_count($pageUrl, 'order_id=');
    $upsellLevel = max(0, $occurrences - 1);

    $name      = trim((string)($block['creditcards_name'] ?? ''));
    $email     = trim((string)($block['emailaddress'] ?? ''));
    $item      = trim((string)($block['item'] ?? ''));
    $country   = trim((string)($block['country'] ?? $block['addresses_country'] ?? ''));
    $zip       = trim((string)($block['zip'] ?? ''));
    $city      = trim((string)($block['city'] ?? ''));
    $address   = trim((string)($block['address'] ?? ''));
    $phone     = trim((string)($block['phone'] ?? ''));
    $price     = (float)($block['price'] ?? 0);
    $total     = (float)($block['total'] ?? 0);
    $extOrder  = trim((string)($block['external_order_id'] ?? ''));

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // ---- Resolve affiliate ----
    // Priority: aff_id passed by JS → sessid2 cookie lookup → recent IP-based traffic.
    $affId = $affIdJs;
    $subId = $subIdJs;

    if (empty($affId) && !empty($sessid2)) {
        $stmt = $pdo->prepare("SELECT aff_id, sub_id FROM affiliate_traffic WHERE domain_id = ? AND sessid2 = ? AND aff_id IS NOT NULL AND aff_id != '' ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$domainId, $sessid2]);
        if ($row = $stmt->fetch()) {
            $affId = $affId ?: $row['aff_id'];
            $subId = $subId ?: $row['sub_id'];
        }
    }
    if (empty($affId) && !empty($ip)) {
        $stmt = $pdo->prepare("SELECT aff_id, sub_id FROM affiliate_traffic WHERE domain_id = ? AND ip_address = ? AND page_type = 'landing' AND aff_id IS NOT NULL AND aff_id != '' AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY timestamp DESC LIMIT 1");
        $stmt->execute([$domainId, $ip]);
        if ($row = $stmt->fetch()) {
            $affId = $affId ?: $row['aff_id'];
            $subId = $subId ?: $row['sub_id'];
        }
    }

    // Look up affiliate name for convenience
    $affName = '';
    if (!empty($affId)) {
        $stmt = $pdo->prepare("SELECT name FROM affiliate_names WHERE domain_id = ? AND aff_id = ? LIMIT 1");
        $stmt->execute([$domainId, $affId]);
        if ($n = $stmt->fetchColumn()) $affName = $n;
    }

    // ---- Insert / update ----
    // CSV upload is the source of truth for refunds / fulfillment, so on duplicate
    // we only update fields the URL definitively provides — never overwrite
    // status, fulfillment, delay-mail flags etc.
    $sql = "INSERT INTO orders
            (domain_id, order_id, order_id_global, date_created, status,
             customer_name, customer_email, customer_phone, address, city, country, zip,
             affiliate_id, affiliate_name, sub_id,
             product_codenames, total_amount, ip_address, external_order_id, referrer_url,
             flag_upsell, flag_upsell_level, capture_source)
            VALUES (?, ?, ?, NOW(), 'sale',
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, 'url_capture')
            ON DUPLICATE KEY UPDATE
                order_id_global   = COALESCE(NULLIF(VALUES(order_id_global), ''), order_id_global),
                customer_name     = COALESCE(NULLIF(VALUES(customer_name), ''),  customer_name),
                customer_email    = COALESCE(NULLIF(VALUES(customer_email), ''), customer_email),
                customer_phone    = COALESCE(NULLIF(VALUES(customer_phone), ''), customer_phone),
                address           = COALESCE(NULLIF(VALUES(address), ''),        address),
                city              = COALESCE(NULLIF(VALUES(city), ''),           city),
                country           = COALESCE(NULLIF(VALUES(country), ''),        country),
                zip               = COALESCE(NULLIF(VALUES(zip), ''),            zip),
                affiliate_id      = COALESCE(NULLIF(VALUES(affiliate_id), ''),   affiliate_id),
                affiliate_name    = COALESCE(NULLIF(VALUES(affiliate_name), ''), affiliate_name),
                product_codenames = COALESCE(NULLIF(VALUES(product_codenames), ''), product_codenames),
                total_amount      = IF(total_amount = 0, VALUES(total_amount), total_amount),
                ip_address        = COALESCE(NULLIF(VALUES(ip_address), ''),     ip_address),
                flag_upsell       = GREATEST(flag_upsell, VALUES(flag_upsell)),
                flag_upsell_level = GREATEST(flag_upsell_level, VALUES(flag_upsell_level))";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $domainId, $orderId, $orderIdGlobal,
        $name, $email, $phone, $address, $city, $country, $zip,
        $affId, $affName, $subId,
        $item, $total, $ip, $extOrder, $referrer,
        $upsellLevel > 0 ? 1 : 0, $upsellLevel,
    ]);

    $action = $stmt->rowCount() === 1 ? 'inserted' : 'updated';

    echo json_encode([
        'success'      => true,
        'order_id'     => $orderId,
        'upsell_level' => $upsellLevel,
        'action_taken' => $action,
    ]);
}

// ================================================================
// API KEY MANAGEMENT
// ================================================================

function createApiKey($pdo) {
    $data     = getPostData();
    $label    = trim($data['label'] ?? '');
    $domainId = isset($data['domain_id']) && $data['domain_id'] !== '' ? (int)$data['domain_id'] : null;

    if (empty($label)) {
        echo json_encode(['success' => false, 'error' => 'Label is required']); return;
    }

    // Generate a cryptographically random key: sk_ + 48 hex chars (24 random bytes)
    $rawHex  = bin2hex(random_bytes(24));
    $fullKey = 'sk_' . $rawHex;
    $prefix  = 'sk_' . substr($rawHex, 0, 8); // display prefix
    $hash    = hash('sha256', $fullKey);

    // Detect full_key column (added by migrate-api-keys-full-key.php). If migration
    // hasn't run yet, insert without it so creation still works (key just won't be
    // copyable from the UI later, same as legacy pre-migration keys).
    $hasFullKey = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM api_keys LIKE 'full_key'")->fetch();
        $hasFullKey = $col !== false;
    } catch (Exception $e) {
        $hasFullKey = false;
    }

    if ($hasFullKey) {
        $stmt = $pdo->prepare("
            INSERT INTO api_keys (label, domain_id, key_prefix, key_hash, full_key, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$label, $domainId, $prefix, $hash, $fullKey]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO api_keys (label, domain_id, key_prefix, key_hash, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$label, $domainId, $prefix, $hash]);
    }
    $id = (int)$pdo->lastInsertId();

    // Return the full key ONCE — it is never retrievable again
    echo json_encode([
        'success'    => true,
        'id'         => $id,
        'full_key'   => $fullKey,
        'key_prefix' => $prefix,
        'label'      => $label,
        'domain_id'  => $domainId,
    ]);
}

function listApiKeys($pdo) {
    $data     = getPostData();
    $domainId = $data['domain_id'] ?? null;

    // Detect whether the full_key column exists (added by migrate-api-keys-full-key.php).
    // If not yet migrated, fall back to SELECT without it so the UI still works.
    $hasFullKey = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM api_keys LIKE 'full_key'")->fetch();
        $hasFullKey = $col !== false;
    } catch (Exception $e) {
        $hasFullKey = false;
    }

    $cols = "k.id, k.label, k.domain_id, d.label AS domain_label, k.key_prefix, k.status, k.created_at, k.last_used_at"
          . ($hasFullKey ? ", k.full_key" : "");

    if (!empty($domainId)) {
        $stmt = $pdo->prepare("
            SELECT $cols
            FROM api_keys k
            LEFT JOIN domains d ON d.id = k.domain_id
            WHERE k.domain_id = ?
            ORDER BY k.created_at DESC
        ");
        $stmt->execute([(int)$domainId]);
    } else {
        $stmt = $pdo->query("
            SELECT $cols
            FROM api_keys k
            LEFT JOIN domains d ON d.id = k.domain_id
            ORDER BY k.created_at DESC
        ");
    }

    echo json_encode(['success' => true, 'keys' => $stmt->fetchAll(), 'migrated' => $hasFullKey]);
}

function revokeApiKey($pdo) {
    $data = getPostData();
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); return; }

    $pdo->prepare("UPDATE api_keys SET status = 'revoked' WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}

function deleteApiKey($pdo) {
    $data = getPostData();
    $id   = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'error' => 'Missing id']); return; }

    $pdo->prepare("DELETE FROM api_keys WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}
?>

<?php
// ============================================================
// POST /api/claims        — Submit a claim (multipart/form-data)
// GET  /api/claims        — List customer's own claims
// GET  /api/claims/:id    — Single claim detail
// ============================================================
require_once __DIR__ . '/../middleware/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../middleware/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── POST: Submit a claim ─────────────────────────────────────
if ($method === 'POST') {
    $auth        = require_auth();
    $customer_id = (int) $auth['sub'];

    // Decode multipart fields from $_POST
    $policy_id      = (int)    ($_POST['policy_id']      ?? 0);
    $rental_company = sanitize($_POST['rental_company']  ?? '');
    $incident_date  = sanitize($_POST['incident_date']   ?? '');
    $damage_types   = sanitize($_POST['damage_types']    ?? '');  // JSON string
    $description    = sanitize($_POST['description']     ?? '');
    $amount_charged = (float)  ($_POST['amount_charged'] ?? 0);

    if (!$policy_id)      json_error('Policy ID is required.', 422);
    if (!$incident_date)  json_error('Incident date is required.', 422);
    if (!$description)    json_error('Description is required.', 422);
    if ($amount_charged <= 0) json_error('Amount charged must be greater than 0.', 422);

    $db = Database::get();

    // Verify policy belongs to customer and is active
    $stmt = $db->prepare(
        'SELECT id, policy_number, coverage_amount, status, end_date
         FROM policies WHERE id = ? AND customer_id = ? LIMIT 1'
    );
    $stmt->execute([$policy_id, $customer_id]);
    $policy = $stmt->fetch();

    if (!$policy) json_error('Policy not found or does not belong to your account.', 404);
    
    if ($policy['end_date'] < date('Y-m-d')) {
        json_error('Your coverage has expired. You cannot submit claims for expired policies.', 409);
    }
    if ($amount_charged > $policy['coverage_amount']) {
        json_error('Amount claimed exceeds your coverage limit of $' . number_format($policy['coverage_amount']), 422);
    }
    
    // Check if a claim is currently pending (no final result yet)
    $claimCountStmt = $db->prepare('SELECT COUNT(*) FROM claims WHERE policy_id = ? AND status IN ("submitted", "under_review", "approved")');
    $claimCountStmt->execute([$policy_id]);
    $existingClaims = (int) $claimCountStmt->fetchColumn();
    if ($existingClaims > 0) {
        json_error('You already have a pending claim for this policy. You can submit another claim once the current one is resolved.', 409);
    }

    // Generate unique claim number
    do {
        $claim_number = generate_claim_number();
        $dup = $db->prepare('SELECT id FROM claims WHERE claim_number = ?');
        $dup->execute([$claim_number]);
    } while ($dup->fetch());

    // Begin transaction
    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO claims
             (customer_id, policy_id, claim_number, rental_company,
              incident_date, damage_types, description, amount_claimed,
              status, created_at)
             VALUES (?,?,?,?,?,?,?,?,"submitted",NOW())'
        );
        $stmt->execute([
            $customer_id, $policy_id, $claim_number, $rental_company,
            $incident_date, $damage_types, $description, $amount_charged,
        ]);
        $claim_id = (int) $db->lastInsertId();

        // Handle file uploads
        $upload_errors = [];
        $allowed_types = ALLOWED_TYPES;
        $allowed_exts  = ALLOWED_EXTENSIONS;
        $upload_base   = UPLOAD_DIR . 'claims/' . $claim_id . '/';

        if (!is_dir($upload_base)) mkdir($upload_base, 0755, true);

        $doc_types = ['rental_agreement', 'invoice', 'driver_licence', 'damage_photos', 'other'];
        foreach ($doc_types as $doc_type) {
            if (!isset($_FILES[$doc_type])) continue;

            $files = $_FILES[$doc_type];
            // Normalize single vs multiple files
            if (!is_array($files['name'])) {
                $files = array_map(function($v) { return [$v]; }, $files);
            }

            $file_count = count($files['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                if ($files['size'][$i] > MAX_FILE_SIZE) {
                    $upload_errors[] = "File too large: {$files['name'][$i]}";
                    continue;
                }

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = $finfo->file($files['tmp_name'][$i]);
                if (!in_array($mime, $allowed_types, true)) {
                    $upload_errors[] = "Invalid file type: {$files['name'][$i]}";
                    continue;
                }

                $ext      = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $filename = $doc_type . '_' . $i . '_' . time() . '.' . $ext;
                $dest     = $upload_base . $filename;

                if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                    $db->prepare(
                        'INSERT INTO claim_documents (claim_id, document_type, file_name, file_path, file_size, mime_type, uploaded_at)
                         VALUES (?,?,?,?,?,?,NOW())'
                    )->execute([
                        $claim_id, $doc_type, $files['name'][$i],
                        'uploads/claims/' . $claim_id . '/' . $filename,
                        $files['size'][$i], $mime,
                    ]);
                }
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        json_error('Failed to submit claim. Please try again.', 500);
    }

    $warnings = $upload_errors ? ['upload_warnings' => $upload_errors] : null;

    json_success(array_merge([
        'claim_id'     => $claim_id,
        'claim_number' => $claim_number,
        'status'       => 'submitted',
        'policy_id'    => $policy_id,
    ], $warnings ?? []), 'Claim submitted successfully. We will acknowledge within 24 hours.', 201);
}

// ── GET: List claims ──────────────────────────────────────────
if ($method === 'GET') {
    $auth        = require_auth();
    $customer_id = (int) $auth['sub'];
    $page_data   = paginate((int) ($_GET['page'] ?? 1));
    $status      = $_GET['status'] ?? null;

    $db    = Database::get();
    $where = 'WHERE cl.customer_id = ?';
    $params = [$customer_id];
    if ($status) { $where .= ' AND cl.status = ?'; $params[] = $status; }

    $count = $db->prepare("SELECT COUNT(*) FROM claims cl $where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $db->prepare(
        "SELECT cl.*, p.policy_number, COUNT(cd.id) as document_count
         FROM claims cl
         LEFT JOIN policies p ON p.id = cl.policy_id
         LEFT JOIN claim_documents cd ON cd.claim_id = cl.id
         $where
         GROUP BY cl.id
         ORDER BY cl.created_at DESC
         LIMIT {$page_data['per_page']} OFFSET {$page_data['offset']}"
    );
    $stmt->execute($params);
    $claims = $stmt->fetchAll();

    foreach ($claims as &$c) {
        $c['amount_claimed'] = (float) $c['amount_claimed'];
        $c['amount_paid']    = (float) ($c['amount_paid'] ?? 0);
        $c['document_count'] = (int)   $c['document_count'];
    }

    json_paginated($claims, $total, $page_data['page'], $page_data['per_page']);
}

json_error('Method not allowed.', 405);

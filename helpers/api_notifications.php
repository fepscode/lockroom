<?php
// Unified Realtime Notification Endpoint for LOCK & ROOM
// Supports both OWNER (Pending Actions) and TENANT (Approval & Status Updates)
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user = currentUser();
$pdo = getDBConnection();
$role = $user['role'];

try {
    // ================= 1. OWNER NOTIFICATIONS =================
    if ($role === 'pemilik') {
        // Pending Payments
        $stmtBills = $pdo->prepare("SELECT b.id, b.bill_code, b.title, b.amount, b.status, 
                                           p.name as property_name, r.room_number, u.name as tenant_name,
                                           pm.payment_date, pm.payment_method, pm.proof_image
                                    FROM bills b
                                    JOIN leases l ON b.lease_id = l.id
                                    JOIN rooms r ON l.room_id = r.id
                                    JOIN properties p ON r.property_id = p.id
                                    JOIN users u ON b.tenant_id = u.id
                                    LEFT JOIN payments pm ON b.id = pm.bill_id
                                    WHERE p.owner_id = ? 
                                      AND b.status = 'menunggu_verifikasi'
                                    ORDER BY b.id DESC
                                    LIMIT 5");
        $stmtBills->execute([$user['id']]);
        $pendingBills = $stmtBills->fetchAll();

        // Pending Facility Complaints
        $stmtComplaints = $pdo->prepare("SELECT c.id, c.title, c.description, c.priority, c.status, c.created_at,
                                                p.name as property_name, r.room_number, u.name as tenant_name, u.phone as tenant_phone
                                         FROM complaints c
                                         JOIN rooms r ON c.room_id = r.id
                                         JOIN properties p ON r.property_id = p.id
                                         JOIN users u ON c.tenant_id = u.id
                                         WHERE p.owner_id = ?
                                           AND c.status = 'menunggu'
                                         ORDER BY c.id DESC
                                         LIMIT 5");
        $stmtComplaints->execute([$user['id']]);
        $pendingComplaints = $stmtComplaints->fetchAll();

        $billCount = count($pendingBills);
        $complaintCount = count($pendingComplaints);

        echo json_encode([
            'status' => 'success',
            'role' => 'pemilik',
            'total_count' => $billCount + $complaintCount,
            'bill_count' => $billCount,
            'complaint_count' => $complaintCount,
            'bills' => $pendingBills,
            'complaints' => $pendingComplaints,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // ================= 2. TENANT NOTIFICATIONS =================
    if ($role === 'penyewa') {
        // Fetch approved (LUNAS) bills and rejected bills for tenant
        $stmtApprovedBills = $pdo->prepare("SELECT b.id, b.bill_code, b.title, b.amount, b.status, 
                                                   pm.payment_date, pm.verified_at
                                            FROM bills b
                                            LEFT JOIN payments pm ON b.id = pm.bill_id
                                            WHERE b.tenant_id = ? 
                                              AND b.status = 'lunas'
                                            ORDER BY b.id DESC
                                            LIMIT 5");
        $stmtApprovedBills->execute([$user['id']]);
        $approvedBills = $stmtApprovedBills->fetchAll();

        // Fetch rejected bills
        $stmtRejectedBills = $pdo->prepare("SELECT b.id, b.bill_code, b.title, b.amount, b.status 
                                            FROM bills b
                                            WHERE b.tenant_id = ? AND b.status = 'ditolak'
                                            ORDER BY b.id DESC LIMIT 5");
        $stmtRejectedBills->execute([$user['id']]);
        $rejectedBills = $stmtRejectedBills->fetchAll();

        // Fetch processed or resolved complaints
        $stmtActiveComplaints = $pdo->prepare("SELECT c.id, c.title, c.description, c.status, c.admin_response, c.updated_at 
                                               FROM complaints c
                                               WHERE c.tenant_id = ? 
                                                 AND c.status IN ('diproses', 'selesai')
                                               ORDER BY c.updated_at DESC LIMIT 5");
        $stmtActiveComplaints->execute([$user['id']]);
        $activeComplaints = $stmtActiveComplaints->fetchAll();

        $approvedCount = count($approvedBills);
        $rejectedCount = count($rejectedBills);
        $complaintUpdateCount = count($activeComplaints);

        echo json_encode([
            'status' => 'success',
            'role' => 'penyewa',
            'approved_bills' => $approvedBills,
            'rejected_bills' => $rejectedBills,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'complaints' => $activeComplaints,
            'complaint_count' => $complaintUpdateCount,
            'total_count' => $approvedCount + $rejectedCount + $complaintUpdateCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

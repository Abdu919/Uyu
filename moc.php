<?php
// api/moc.php
require_once 'config.php';
requireAuth();
requireRole(['moc', 'admin']);

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'applications':
            getApplications();
            break;
        case 'crops':
            listCrops();
            break;
        default:
            sendJSON(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'update_application':
            updateApplication();
            break;
        case 'register_importer':
            registerImporter();
            break;
        case 'add_snapshot':
            addMarketSnapshot();
            break;
        case 'add_forecast':
            addMarketForecast();
            break;
        default:
            sendJSON(['status' => 'error', 'message' => 'Invalid action'], 400);
    }
} else {
    sendJSON(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

function listCrops() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT crop_id, crop_name FROM crop ORDER BY crop_name");
    $crops = $stmt->fetchAll();
    sendJSON(['status' => 'success', 'crops' => $crops]);
}

function getApplications() {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT 
            ia.importer_id,
            ia.crop_id,
            ia.arrival_date,
            ia.requested_quantity_tons,
            ia.status,
            ia.approved_quantity,
            ia.notes,
            i.company_name,
            c.crop_name
        FROM import_application ia
        JOIN importer i ON ia.importer_id = i.user_id
        JOIN crop c ON ia.crop_id = c.crop_id
        ORDER BY ia.arrival_date ASC
    ");
    $applications = $stmt->fetchAll();

    foreach ($applications as &$app) {
        // Create a unique composite ID using underscores
        $app['application_id'] = $app['importer_id'] . '_' . $app['crop_id'] . '_' . $app['arrival_date'];

        // Get Local Supply overlap
        $stmtOverlap = $pdo->prepare("
            SELECT SUM(expected_yield_tons) as total_local
            FROM production_forecast
            WHERE crop_id = ?
            AND harvest_start_date <= ? 
            AND harvest_end_date >= ?
        ");
        $arrival = $app['arrival_date'];
        $stmtOverlap->execute([$app['crop_id'], $arrival, $arrival]);
        $local = $stmtOverlap->fetchColumn();
        $app['local_supply'] = $local ?: 0;

        // FIX: Corrected Demand Query to bypass the NULL region issue
        $stmtDemand = $pdo->prepare("
            SELECT demand FROM (
                SELECT demand_estimate as demand, snapshot_period as period 
                FROM market_snapshot 
                WHERE crop_id = ?
                
                UNION ALL
                
                SELECT forecast_demand as demand, forecast_period as period 
                FROM market_forecast 
                WHERE crop_id = ?
            ) AS combined_data
            ORDER BY period DESC 
            LIMIT 1
        ");
        
        $stmtDemand->execute([$app['crop_id'], $app['crop_id']]);
        $demandRow = $stmtDemand->fetch();
        $demand = $demandRow ? floatval($demandRow['demand']) : 0;
        $app['demand'] = $demand;

        // Recommendation Logic
        if ($app['local_supply'] > 0) {
            if ($app['local_supply'] >= $demand) {
                $app['conflict_level'] = 'high';
                $app['recommendation'] = 'Reject – local supply sufficient';
            } else {
                $gap = $demand - $app['local_supply'];
                $app['conflict_level'] = 'moderate';
                $app['recommendation'] = "Cap at {$gap} tons (local deficit)";
            }
        } else {
            $app['conflict_level'] = 'none';
            $app['recommendation'] = 'Approve – no local harvest overlap';
        }
    }

    sendJSON(['status' => 'success', 'applications' => $applications]);
}

function updateApplication() {
    $data = json_decode(file_get_contents('php://input'), true);
    $appIdStr = $data['application_id'] ?? '';
    $status = $data['status'] ?? null;
    $approvedQty = null;
    
    if (array_key_exists('approved_quantity', $data) && $data['approved_quantity'] !== null && $data['approved_quantity'] !== '') {
        $approvedQty = floatval($data['approved_quantity']);
    }

    if (!$appIdStr || !$status) {
        sendJSON(['status' => 'error', 'message' => 'Missing required fields'], 400);
    }

    $parts = explode('_', $appIdStr);
    if (count($parts) !== 3) {
        sendJSON(['status' => 'error', 'message' => 'Invalid application ID'], 400);
    }
    
    $importerId = $parts[0];
    $cropId = $parts[1];
    $arrivalDate = $parts[2];

    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            UPDATE import_application 
            SET status = ?, approved_quantity = ?
            WHERE importer_id = ? AND crop_id = ? AND arrival_date = ?
        ");
        $stmt->execute([$status, $approvedQty, $importerId, $cropId, $arrivalDate]);
        sendJSON(['status' => 'success', 'message' => 'Application updated']);
    } catch (PDOException $e) {
        sendJSON(['status' => 'error', 'message' => 'Database error'], 500);
    }
}

function registerImporter() {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $full_name = trim($data['full_name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $company_name = trim($data['company_name'] ?? '');

    if (!$username || !$password || !$full_name || !$email || !$company_name) {
        sendJSON(['status' => 'error', 'message' => 'All fields required'], 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo = getDBConnection();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO user (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'importer')");
        $stmt->execute([$username, $hash, $full_name, $email, $phone]);
        $userId = $pdo->lastInsertId();

        $stmt2 = $pdo->prepare("INSERT INTO importer (user_id, company_name) VALUES (?, ?)");
        $stmt2->execute([$userId, $company_name]);

        $pdo->commit();
        sendJSON(['status' => 'success', 'message' => 'Importer registered']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->errorInfo[1] == 1062) {
            sendJSON(['status' => 'error', 'message' => 'Username or email already exists'], 409);
        }
        sendJSON(['status' => 'error', 'message' => 'Database error'], 500);
    }
}

function addMarketSnapshot() {
    $data = json_decode(file_get_contents('php://input'), true);
    $cropId = $data['crop_id'] ?? 0;
    $region = isset($data['region']) && trim($data['region']) !== '' ? trim($data['region']) : 'National';
    $period = $data['snapshot_period'] ?? '';
    $price = isset($data['price']) && $data['price'] !== '' ? floatval($data['price']) : null;
    $demand = floatval($data['demand_estimate'] ?? 0);

    if (!$cropId || !$period || !$demand) {
        sendJSON(['status' => 'error', 'message' => 'Crop, period, and demand required'], 400);
    }

    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO market_snapshot (crop_id, region, snapshot_period, price, demand_estimate)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cropId, $region, $period, $price, $demand]);
        sendJSON(['status' => 'success', 'message' => 'Snapshot added']);
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            sendJSON(['status' => 'error', 'message' => 'Snapshot for this crop/region/period already exists'], 409);
        }
        sendJSON(['status' => 'error', 'message' => 'Database error'], 500);
    }
}

function addMarketForecast() {
    $data = json_decode(file_get_contents('php://input'), true);
    $cropId = $data['crop_id'] ?? 0;
    $region = isset($data['region']) && trim($data['region']) !== '' ? trim($data['region']) : 'National';
    $period = $data['forecast_period'] ?? '';
    $demand = floatval($data['forecast_demand'] ?? 0);

    if (!$cropId || !$period || !$demand) {
        sendJSON(['status' => 'error', 'message' => 'Crop, period, and demand required'], 400);
    }

    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO market_forecast (crop_id, region, forecast_period, forecast_demand)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$cropId, $region, $period, $demand]);
        sendJSON(['status' => 'success', 'message' => 'Forecast added']);
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            sendJSON(['status' => 'error', 'message' => 'Forecast for this crop/region/period already exists'], 409);
        }
        sendJSON(['status' => 'error', 'message' => 'Database error'], 500);
    }
}
?>
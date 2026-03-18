<?php
// api/farmer.php
require_once 'config.php';
requireAuth();
requireRole(['farmer', 'admin']);

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'crops') listCrops();
        elseif ($action === 'forecasts') getForecasts();
            elseif ($action === 'recommendations') getRecommendations();
                else sendJSON(['status' => 'error', 'message' => 'Invalid action'], 400);
                } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_forecast') {
                    submitForecast();
                    } else {
                        sendJSON(['status' => 'error', 'message' => 'Method not allowed'], 405);
                        }

                        function listCrops() {
                            $pdo = getDBConnection();
                                $stmt = $pdo->query("SELECT crop_id, crop_name FROM crop ORDER BY crop_name");
                                    $crops = $stmt->fetchAll();
                                        sendJSON(['status' => 'success', 'crops' => $crops]);
                                        }

                                        function getForecasts() {
                                            $userId = $_SESSION['user_id'];
                                                $pdo = getDBConnection();
                                                    $stmt = $pdo->prepare("
                                                            SELECT pf.*, c.crop_name
                                                                    FROM production_forecast pf
                                                                            JOIN crop c ON pf.crop_id = c.crop_id
                                                                                    WHERE pf.farmer_id = ?
                                                                                            ORDER BY pf.harvest_start_date
                                                                                                ");
                                                                                                    $stmt->execute([$userId]);
                                                                                                        $forecasts = $stmt->fetchAll();
                                                                                                            sendJSON(['status' => 'success', 'forecasts' => $forecasts]);
                                                                                                            }

                                                                                                            function submitForecast() {
                                                                                                                $data = json_decode(file_get_contents('php://input'), true);
                                                                                                                    $farmerId = $_SESSION['user_id'];
                                                                                                                        $cropId = $data['crop_id'] ?? 0;
                                                                                                                            $yield = $data['expected_yield_tons'] ?? 0;
                                                                                                                                $start = $data['harvest_start_date'] ?? '';
                                                                                                                                    $end = $data['harvest_end_date'] ?? '';

                                                                                                                                        if (!$cropId || !$yield || !$start || !$end) {
                                                                                                                                                sendJSON(['status' => 'error', 'message' => 'All fields required'], 400);
                                                                                                                                                    }

                                                                                                                                                        $pdo = getDBConnection();
                                                                                                                                                            try {
                                                                                                                                                                    $stmt = $pdo->prepare("
                                                                                                                                                                                INSERT INTO production_forecast (farmer_id, crop_id, expected_yield_tons, harvest_start_date, harvest_end_date)
                                                                                                                                                                                            VALUES (?, ?, ?, ?, ?)
                                                                                                                                                                                                    ");
                                                                                                                                                                                                            $stmt->execute([$farmerId, $cropId, $yield, $start, $end]);
                                                                                                                                                                                                                    sendJSON(['status' => 'success', 'message' => 'Forecast submitted']);
                                                                                                                                                                                                                        } catch (PDOException $e) {
                                                                                                                                                                                                                                sendJSON(['status' => 'error', 'message' => 'Database error'], 500);
                                                                                                                                                                                                                                    }
                                                                                                                                                                                                                                    }

                                                                                                                                                                                                                                    function getRecommendations() {
                                                                                                                                                                                                                                        $farmerId = $_SESSION['user_id'];
                                                                                                                                                                                                                                            $pdo = getDBConnection();
                                                                                                                                                                                                                                                // Get recommendations targeted to this farmer OR general (farmer_id IS NULL)
                                                                                                                                                                                                                                                    $stmt = $pdo->prepare("
                                                                                                                                                                                                                                                            SELECT r.*, c.crop_name
                                                                                                                                                                                                                                                                    FROM recommendation r
                                                                                                                                                                                                                                                                            JOIN crop c ON r.crop_id = c.crop_id
                                                                                                                                                                                                                                                                                    WHERE (r.farmer_id = ? OR r.farmer_id IS NULL) AND r.is_active = TRUE
                                                                                                                                                                                                                                                                                            ORDER BY r.priority_level DESC, r.recommendation_id DESC
                                                                                                                                                                                                                                                                                                ");
                                                                                                                                                                                                                                                                                                    $stmt->execute([$farmerId]);
                                                                                                                                                                                                                                                                                                        $recs = $stmt->fetchAll();
                                                                                                                                                                                                                                                                                                            sendJSON(['status' => 'success', 'recommendations' => $recs]);
                                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                                            ?>

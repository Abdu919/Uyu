<?php
// api/config.php
define('DB_HOST', 'mysql');
define('DB_NAME', 'AgriBalance');
define('DB_USER', 'root');
define('DB_PASS', 'root'); // set your password

function getDBConnection() {
    static $pdo = null;
        if ($pdo === null) {
                try {
                            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                                        $pdo = new PDO($dsn, DB_USER, DB_PASS);
                                                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                                                                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                                                                        } catch (PDOException $e) {
                                                                                    sendJSON(['status' => 'error', 'message' => 'Database connection failed'], 500);
                                                                                            }
                                                                                                }
                                                                                                    return $pdo;
                                                                                                    }

                                                                                                    function sendJSON($data, $statusCode = 200) {
                                                                                                        http_response_code($statusCode);
                                                                                                            header('Content-Type: application/json; charset=utf-8');
                                                                                                                echo json_encode($data, JSON_UNESCAPED_UNICODE);
                                                                                                                    exit;
                                                                                                                    }

                                                                                                                    function requireAuth() {
                                                                                                                        session_start();
                                                                                                                            if (!isset($_SESSION['user_id'])) {
                                                                                                                                    sendJSON(['status' => 'error', 'message' => 'Unauthorized'], 401);
                                                                                                                                        }
                                                                                                                                        }

                                                                                                                                        function requireRole($allowedRoles) {
                                                                                                                                            if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowedRoles)) {
                                                                                                                                                    sendJSON(['status' => 'error', 'message' => 'Forbidden'], 403);
                                                                                                                                                        }
                                                                                                                                                        }
                                                                                                                                                        ?>
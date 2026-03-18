<?php
// api/admin.php
require_once 'config.php';
requireAuth();
requireRole(['admin']);

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    listUsers();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create') createUser();
            elseif ($action === 'update_status') updateStatus();
                else sendJSON(['status' => 'error', 'message' => 'Invalid action'], 400);
                } else {
                    sendJSON(['status' => 'error', 'message' => 'Method not allowed'], 405);
                    }

                    function listUsers() {
                        $pdo = getDBConnection();
                            $stmt = $pdo->query("SELECT user_id, username, full_name, email, role, status FROM user ORDER BY user_id");
                                $users = $stmt->fetchAll();
                                    sendJSON(['status' => 'success', 'users' => $users]);
                                    }

                                    function createUser() {
                                        $data = json_decode(file_get_contents('php://input'), true);
                                            $username = trim($data['username'] ?? '');
                                                $password = $data['password'] ?? '';
                                                    $full_name = trim($data['full_name'] ?? '');
                                                        $email = trim($data['email'] ?? '');
                                                            $role = $data['role'] ?? '';

                                                                if (!$username || !$password || !$full_name || !$email || !$role) {
                                                                        sendJSON(['status' => 'error', 'message' => 'All fields required'], 400);
                                                                            }

                                                                                $hash = password_hash($password, PASSWORD_DEFAULT);
                                                                                    $pdo = getDBConnection();
                                                                                        try {
                                                                                                $pdo->beginTransaction();
                                                                                                        $stmt = $pdo->prepare("INSERT INTO user (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)");
                                                                                                                $stmt->execute([$username, $hash, $full_name, $email, $role]);
                                                                                                                        $userId = $pdo->lastInsertId();

                                                                                                                                // Insert into role-specific table if needed (but not required for admin/mewa/moc? Actually mewa/moc need entries)
                                                                                                                                        if ($role === 'mewa') {
                                                                                                                                                    $pdo->prepare("INSERT INTO mewa_employee (user_id, region, department) VALUES (?, '', '')")->execute([$userId]);
                                                                                                                                                            } elseif ($role === 'moc') {
                                                                                                                                                                        $pdo->prepare("INSERT INTO moc_employee (user_id, region, department) VALUES (?, '', '')")->execute([$userId]);
                                                                                                                                                                                } elseif ($role === 'farmer') {
                                                                                                                                                                                            $pdo->prepare("INSERT INTO farmer (user_id, region, farm_size_hectares) VALUES (?, '', 0)")->execute([$userId]);
                                                                                                                                                                                                    } elseif ($role === 'importer') {
                                                                                                                                                                                                                $pdo->prepare("INSERT INTO importer (user_id, company_name) VALUES (?, '')")->execute([$userId]);
                                                                                                                                                                                                                        }

                                                                                                                                                                                                                                $pdo->commit();
                                                                                                                                                                                                                                        sendJSON(['status' => 'success', 'message' => 'User created']);
                                                                                                                                                                                                                                            } catch (PDOException $e) {
                                                                                                                                                                                                                                                    $pdo->rollBack();
                                                                                                                                                                                                                                                            if ($e->errorInfo[1] == 1062) {
                                                                                                                                                                                                                                                                        sendJSON(['status' => 'error', 'message' => 'Username or email already exists'], 409);
                                                                                                                                                                                                                                                                                }
                                                                                                                                                                                                                                                                                        sendJSON(['status' => 'error', 'message' => 'Database error'], 500);
                                                                                                                                                                                                                                                                                            }
                                                                                                                                                                                                                                                                                            }

                                                                                                                                                                                                                                                                                            function updateStatus() {
                                                                                                                                                                                                                                                                                                $data = json_decode(file_get_contents('php://input'), true);
                                                                                                                                                                                                                                                                                                    $userId = $data['user_id'] ?? 0;
                                                                                                                                                                                                                                                                                                        $status = $data['status'] ?? '';
                                                                                                                                                                                                                                                                                                            if (!$userId || !in_array($status, ['active','inactive'])) {
                                                                                                                                                                                                                                                                                                                    sendJSON(['status' => 'error', 'message' => 'Invalid data'], 400);
                                                                                                                                                                                                                                                                                                                        }
                                                                                                                                                                                                                                                                                                                            $pdo = getDBConnection();
                                                                                                                                                                                                                                                                                                                                $stmt = $pdo->prepare("UPDATE user SET status = ? WHERE user_id = ?");
                                                                                                                                                                                                                                                                                                                                    $stmt->execute([$status, $userId]);
                                                                                                                                                                                                                                                                                                                                        sendJSON(['status' => 'success', 'message' => 'Status updated']);
                                                                                                                                                                                                                                                                                                                                        }
                                                                                                                                                                                                                                                                                                                                        ?>

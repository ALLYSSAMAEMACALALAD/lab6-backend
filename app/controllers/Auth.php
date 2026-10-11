<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Auth - login / refresh / logout / me using the LavaLust API library (JWT).
 */
class Auth extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library(['api', 'database']);
    }

    /** CORS preflight - the API library answers OPTIONS in its constructor. */
    public function preflight()
    {
        $this->api->respond(null, 204);
    }

    /** POST /api/login  { "username": "...", "password": "..." } */
    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit(null, 10, 60); // 10 login attempts / minute / IP
        $input = $this->api->body();

        $username = trim($input['username'] ?? '');
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') {
            $this->api->respond_error('Username and password are required', 422);
        }

        $stmt = $this->db->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE username = ? LIMIT 1',
            [$username]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !$user['is_active'] || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid username or password', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id'   => $user['id'],
            'role' => $user['role'],
        ]);

        $this->api->respond([
            'message' => 'Login successful',
            'user'    => [
                'id'       => (int) $user['id'],
                'username' => $user['username'],
                'email'    => $user['email'],
                'role'     => $user['role'],
            ],
            'tokens'  => $tokens,
            // Same tokens at the top level too, so simple clients (like the LavaLust API Tester) can read them
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'token_type'    => $tokens['token_type'],
        ]);
    }

    /** POST /api/refresh  { "refresh_token": "..." } */
    public function refresh()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();
        $this->api->refresh_access_token($input['refresh_token'] ?? '');
    }

    /** POST /api/logout  { "refresh_token": "..." }  (needs a valid access token) */
    public function logout()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();
        $input = $this->api->body();
        if (!empty($input['refresh_token'])) {
            $this->api->revoke_refresh_token($input['refresh_token']);
        }
        $this->api->respond(['message' => 'Logged out']);
    }

    /** GET /api/me */
    public function me()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt();
        $stmt = $this->db->raw(
            'SELECT id, username, email, role, created_at FROM users WHERE id = ? LIMIT 1',
            [$auth['sub']]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user ? $this->api->respond($user) : $this->api->respond_error('User not found', 404);
    }

    /** GET /api/list  - all users (admin only; never returns passwords) */
    public function list_users()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt();
        if (($auth['role'] ?? '') !== 'admin') {
            $this->api->respond_error('Forbidden: admin only', 403);
        }
        $rows = $this->db->raw(
            'SELECT id, username, email, role, is_active, created_at FROM users ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->api->respond(['data' => $rows]);
    }

    // ------------------------------------------------------------------
    // User management (used by the LavaLust API Tester)
    // ------------------------------------------------------------------

    private function user_row($id)
    {
        return $this->db->raw(
            'SELECT id, username, email, role, is_active, created_at, updated_at FROM users WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    private function taken($field, $value, $except_id = 0)
    {
        $row = $this->db->raw(
            "SELECT id FROM users WHERE $field = ? AND id <> ? LIMIT 1",
            [$value, (int) $except_id]
        )->fetch(PDO::FETCH_ASSOC);
        return (bool) $row;
    }

    /** POST /api/create  { username, email, password, role? }  - role is only honoured for an admin token */
    public function create_user()
    {
        $this->api->require_method('POST');
        $input = $this->api->body();

        $is_admin = false;
        if ($this->api->get_bearer_token()) {
            $auth = $this->api->require_jwt();
            $is_admin = ($auth['role'] ?? '') === 'admin';
        }

        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $role     = 'user';
        if ($is_admin && !empty($input['role'])) {
            if (!in_array($input['role'], ['admin', 'moderator', 'user'], true)) {
                $this->api->respond_error('Invalid role', 422);
            }
            $role = $input['role'];
        }

        if ($username === '' || strlen($username) > 50) $this->api->respond_error('username is required (max 50 characters)', 422);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) $this->api->respond_error('A valid email is required', 422);
        if (strlen($password) < 8) $this->api->respond_error('password must be at least 8 characters', 422);
        if ($this->taken('username', $username)) $this->api->respond_error('Username already taken', 409);
        if ($this->taken('email', $email)) $this->api->respond_error('Email already in use', 409);

        $this->db->raw(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
            [$username, $email, password_hash($password, PASSWORD_BCRYPT), $role]
        );
        $this->api->respond(['message' => 'User created', 'user' => $this->user_row($this->db->last_id())], 201);
    }

    /** PUT|PATCH /api/update/{id}  { username?, email?, role? } - own profile, or any user if admin */
    public function update_user($id = null)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!in_array($method, ['PUT', 'PATCH'], true)) $this->api->respond_error('Method Not Allowed', 405);
        $auth = $this->api->require_jwt();

        if (!ctype_digit((string) $id)) $this->api->respond_error('User ID is required', 400);
        $is_admin = ($auth['role'] ?? '') === 'admin';
        if (!$is_admin && (int) $auth['sub'] !== (int) $id) {
            $this->api->respond_error('Forbidden: you can only edit your own profile', 403);
        }
        $user = $this->user_row($id);
        if (!$user) $this->api->respond_error('User not found', 404);

        $input = $this->api->body();
        $set = []; $vals = [];

        if (isset($input['username']) && $input['username'] !== '') {
            $v = trim($input['username']);
            if (strlen($v) > 50) $this->api->respond_error('username too long (max 50)', 422);
            if ($this->taken('username', $v, $id)) $this->api->respond_error('Username already taken', 409);
            $set[] = 'username = ?'; $vals[] = $v;
        }
        if (isset($input['email']) && $input['email'] !== '') {
            $v = trim($input['email']);
            if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 100) $this->api->respond_error('A valid email is required', 422);
            if ($this->taken('email', $v, $id)) $this->api->respond_error('Email already in use', 409);
            $set[] = 'email = ?'; $vals[] = $v;
        }
        if (isset($input['role']) && $input['role'] !== '') {
            if (!$is_admin) $this->api->respond_error('Forbidden: only an admin can change roles', 403);
            if (!in_array($input['role'], ['admin', 'moderator', 'user'], true)) $this->api->respond_error('Invalid role', 422);
            if ((int) $id === (int) $auth['sub'] && $input['role'] !== 'admin') {
                $this->api->respond_error('You cannot remove your own admin role', 422);
            }
            $set[] = 'role = ?'; $vals[] = $input['role'];
        }
        if (!$set) $this->api->respond_error('Nothing to update', 422);

        $set[] = 'updated_at = ?'; $vals[] = date('Y-m-d H:i:s');
        $vals[] = (int) $id;
        $this->db->raw('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = ?', $vals);

        $this->api->respond(['message' => 'User updated', 'user' => $this->user_row($id)]);
    }

    /** DELETE /api/delete/{id}  - admin only, cannot delete yourself */
    public function delete_user($id = null)
    {
        $this->api->require_method('DELETE');
        $auth = $this->api->require_jwt();
        if (($auth['role'] ?? '') !== 'admin') $this->api->respond_error('Forbidden: admin only', 403);
        if (!ctype_digit((string) $id)) $this->api->respond_error('User ID is required', 400);
        if ((int) $id === (int) $auth['sub']) $this->api->respond_error('You cannot delete your own account', 422);
        if (!$this->user_row($id)) $this->api->respond_error('User not found', 404);

        $this->db->raw('DELETE FROM refresh_tokens WHERE user_id = ?', [(int) $id]);
        $this->db->raw('DELETE FROM users WHERE id = ?', [(int) $id]);
        $this->api->respond(['message' => 'User deleted']);
    }
}
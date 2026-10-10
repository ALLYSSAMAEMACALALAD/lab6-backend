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
    $this->api->rate_limit(null, 10, 60);

    $input = $this->api->body();

    $username = trim((string) ($input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    // Validate required fields
    if ($username === '' || $password === '') {
        $this->api->respond_error(
            'Username and password are required',
            422
        );
    }

    // Find the user in the database
    $stmt = $this->db->raw(
        'SELECT id, username, email, password, role, is_active
         FROM users
         WHERE username = ?
         LIMIT 1',
        [$username]
    );

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validate the account and password
    if (
        !$user ||
        (int) $user['is_active'] !== 1 ||
        !password_verify($password, $user['password'])
    ) {
        $this->api->respond_error(
            'Invalid username or password',
            401
        );
    }

    // Generate access and refresh tokens
    $tokens = $this->api->issue_tokens([
        'id'   => (int) $user['id'],
        'role' => $user['role'],
    ]);

    // Return successful login response
    $this->api->respond([
        'message' => 'Login successful',
        'user'    => [
            'id'       => (int) $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
        ],
        'tokens'  => $tokens,
    ]);
}

/** POST /api/refresh { "refresh_token": "..." } */
public function refresh()
{
    $this->api->require_method('POST');

    $input = $this->api->body();

    $this->api->refresh_access_token(
        $input['refresh_token'] ?? ''
    );
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
}

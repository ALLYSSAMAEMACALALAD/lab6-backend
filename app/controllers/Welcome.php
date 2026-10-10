<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Health check - open https://<your-api>/ to confirm the API is running.
 */
class Welcome extends Controller {
	public function index() {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'name'    => 'Product Management API (LavaLust)',
			'status'  => 'running',
			'endpoints' => ['/api/login', '/api/products'],
		]);
	}
}

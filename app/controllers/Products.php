<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Products - CRUD endpoints. Every action requires a valid Bearer (JWT) token.
 */
class Products extends Controller
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

    /** GET /api/products */
    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();

        $rows = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['data' => array_map([$this, 'format'], $rows)]);
    }

    /** GET /api/products/{id} */
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();
        $this->api->respond(['data' => $this->format($this->find_or_404($id))]);
    }

    /** POST /api/products */
    public function store()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();

        $data = $this->validate($this->api->body());

        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)',
            [$data['product_name'], $data['description'], $data['price'], $data['quantity']]
        );
        $new_id = $this->db->last_id();

        $this->api->respond([
            'message' => 'Product created',
            'data'    => $this->format($this->find_or_404($new_id)),
        ], 201);
    }

    /** PUT|PATCH /api/products/{id} */
    public function update($id)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!in_array($method, ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $this->api->require_jwt();

        $current = $this->find_or_404($id);
        $input   = $this->api->body();

        // PATCH may send only some fields: merge with the current values.
        $merged = array_merge([
            'product_name' => $current['product_name'],
            'description'  => $current['description'],
            'price'        => $current['price'],
            'quantity'     => $current['quantity'],
        ], $input);
        $data = $this->validate($merged);

        $this->db->raw(
            'UPDATE products SET product_name = ?, description = ?, price = ?, quantity = ? WHERE id = ?',
            [$data['product_name'], $data['description'], $data['price'], $data['quantity'], (int) $id]
        );

        $this->api->respond([
            'message' => 'Product updated',
            'data'    => $this->format($this->find_or_404($id)),
        ]);
    }

    /** DELETE /api/products/{id} */
    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->api->require_jwt();

        $this->find_or_404($id);
        $this->db->raw('DELETE FROM products WHERE id = ?', [(int) $id]);

        $this->api->respond(['message' => 'Product deleted']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function find_or_404($id)
    {
        if (!ctype_digit((string) $id)) {
            $this->api->respond_error('Invalid product id', 400);
        }
        $row = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->api->respond_error('Product not found', 404);
        }
        return $row;
    }

    private function validate(array $in)
    {
        $name  = trim((string) ($in['product_name'] ?? ''));
        $desc  = (string) ($in['description'] ?? '');
        $price = $in['price'] ?? null;
        $qty   = $in['quantity'] ?? null;

        if ($name === '' || mb_strlen($name) > 100) {
            $this->api->respond_error('product_name is required (max 100 characters)', 422);
        }
        if (!is_numeric($price) || (float) $price < 0 || (float) $price > 99999999.99) {
            $this->api->respond_error('price must be a number between 0 and 99999999.99', 422);
        }
        if (filter_var($qty, FILTER_VALIDATE_INT) === false || (int) $qty < 0) {
            $this->api->respond_error('quantity must be a whole number, 0 or more', 422);
        }

        return [
            'product_name' => $name,
            'description'  => $desc,
            'price'        => number_format((float) $price, 2, '.', ''),
            'quantity'     => (int) $qty,
        ];
    }

    private function format(array $row)
    {
        return [
            'id'           => (int) $row['id'],
            'product_name' => $row['product_name'],
            'description'  => $row['description'],
            'price'        => (float) $row['price'],
            'quantity'     => (int) $row['quantity'],
            'created_at'   => $row['created_at'],
        ];
    }
}

<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */
 
/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/
 
/*
| Always send the CORS header for allowed origins, even when something fails
| (database error, 404, bad secret). Without this the browser hides the real
| error and only shows a vague "Network / CORS error".
*/
$cors_origin  = rtrim($_SERVER['HTTP_ORIGIN'] ?? '', '/');
$cors_allowed = (config_item('security_headers')['cors']['allowed_origins'] ?? []);
if ($cors_origin !== '' && in_array($cors_origin, $cors_allowed, true) && !headers_sent()) {
    header('Access-Control-Allow-Origin: ' . $cors_origin);
    header('Vary: Origin');
}
 
$router->get('/', 'Welcome::index');
 
/*
| -------------------------------------------------------------------
| API ROUTES  (JSON only)
| -------------------------------------------------------------------
| OPTIONS routes answer the browser's CORS preflight request.
*/
// Authentication
$router->post('/api/login',    'Auth::login');
$router->post('/api/refresh',  'Auth::refresh');
$router->post('/api/logout',   'Auth::logout');
$router->get('/api/me',        'Auth::me');
$router->get('/api/profile',   'Auth::me');   // alias used by the LavaLust API Tester
$router->options('/api/profile', 'Auth::preflight');
$router->options('/api/login',   'Auth::preflight');
$router->options('/api/refresh', 'Auth::preflight');
$router->options('/api/logout',  'Auth::preflight');
$router->options('/api/me',      'Auth::preflight');
 
// Products (all protected by JWT)
$router->get('/api/products',           'Products::index');
$router->post('/api/products',          'Products::store');
$router->get('/api/products/{id}',      'Products::show');
$router->put('/api/products/{id}',      'Products::update');
$router->patch('/api/products/{id}',    'Products::update');
$router->delete('/api/products/{id}',   'Products::destroy');
$router->options('/api/products',       'Products::preflight');
$router->options('/api/products/{id}',  'Products::preflight');

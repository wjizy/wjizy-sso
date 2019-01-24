<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return response()->json('fail', 405);
});

$router->get('/jsonp', "SsoController@jsonp");
$router->post('/auth', "SsoController@auth");
$router->get('/quit', "SsoController@quit");

$router->get('/getSsoId/{token}', "SsoController@getSsoId");
$router->get('/iframe/{appid}', "SsoController@iframe");
$router->get('/iframe',function () use ($router){
    return response()->json('fail', 405);
});


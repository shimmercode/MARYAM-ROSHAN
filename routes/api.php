<?php
declare(strict_types=1);

/**
 * Internal REST API (v1). All responses use the
 * {success:true,data:…} / {success:false,error:{code,message}} envelope.
 *
 * @var \App\Core\Router $router
 */

$router->group([
    'prefix'     => '/api/v1',
    'middleware' => ['Installed', 'RateLimit:api,120,60'],
], function ($r): void {
    /* Public (no auth) endpoints used by the website booking widget. */
    $r->get('/public/services', 'Api\PublicApiController@services');
    $r->get('/public/staff', 'Api\PublicApiController@staff');
    $r->get('/public/branches', 'Api\PublicApiController@branches');
    $r->get('/public/slots', 'Api\PublicApiController@slots');
    $r->post('/public/bookings', 'Api\PublicApiController@book')->middleware('RateLimit:apibook,10,600');
});

$router->group([
    'prefix'     => '/api/v1',
    'middleware' => ['Installed', 'Auth', 'RateLimit:api,240,60'],
], function ($r): void {
    $r->get('/me', 'Api\ApiController@me');

    $r->get('/customers', 'Api\ApiController@customers');
    $r->get('/customers/{id}', 'Api\ApiController@customer');

    $r->get('/appointments', 'Api\ApiController@appointments');
    $r->get('/appointments/{id}', 'Api\ApiController@appointment');
    $r->post('/appointments', 'Api\ApiController@storeAppointment')->middleware('Csrf');
    $r->post('/appointments/{id}/status', 'Api\ApiController@appointmentStatus')->middleware('Csrf');

    $r->get('/services', 'Api\ApiController@services');
    $r->get('/staff', 'Api\ApiController@staff');
    $r->get('/slots', 'Api\ApiController@slots');

    $r->get('/invoices', 'Api\ApiController@invoices');
    $r->get('/invoices/{id}', 'Api\ApiController@invoice');

    $r->get('/analytics/kpis', 'Api\ApiController@kpis');
    $r->get('/analytics/series', 'Api\ApiController@series');

    $r->get('/live-status', 'Api\LiveStatusController@index');
    $r->post('/live-status/heartbeat', 'Api\LiveStatusController@heartbeat')->middleware('Csrf');
    $r->post('/live-status/start-service', 'Api\LiveStatusController@startService')->middleware('Csrf');
    $r->post('/live-status/end-service', 'Api\LiveStatusController@endService')->middleware('Csrf');
    $r->post('/live-status/alerts/{id}/acknowledge', 'Api\LiveStatusController@acknowledge')->middleware('Csrf');
    $r->post('/live-status/alerts/{id}/resolve', 'Api\LiveStatusController@resolve')->middleware('Csrf');
    $r->post('/live-status/clock-in', 'Api\LiveStatusController@clockIn')->middleware('Csrf');
    $r->post('/live-status/clock-out', 'Api\LiveStatusController@clockOut')->middleware('Csrf');
    $r->post('/live-status/break-start', 'Api\LiveStatusController@startBreak')->middleware('Csrf');
    $r->post('/live-status/break-end', 'Api\LiveStatusController@endBreak')->middleware('Csrf');

    $r->get('/notifications', 'Api\ApiController@notifications');
    $r->post('/notifications/{id}/read', 'Api\ApiController@readNotification')->middleware('Csrf');
});

<?php
declare(strict_types=1);

/**
 * Web routes. $router is provided by App::loadRoutes().
 *
 * @var \App\Core\Router $router
 */

/* ------------------------------------------------------------------ */
/* Installer                                                           */
/* ------------------------------------------------------------------ */
$router->group(['prefix' => '/install', 'middleware' => ['NotInstalled']], function ($r): void {
    $r->get('/', 'Install\InstallController@welcome');
    $r->get('/requirements', 'Install\InstallController@requirements');
    $r->get('/database', 'Install\InstallController@database');
    $r->post('/database', 'Install\InstallController@saveDatabase')->middleware('Csrf');
    $r->get('/admin', 'Install\InstallController@admin');
    $r->post('/admin', 'Install\InstallController@saveAdmin')->middleware('Csrf');
});
// Shown once right after the lock file is written, so it must stay outside the
// NotInstalled group.
$router->get('/install/finish', 'Install\InstallController@finish');

/* ------------------------------------------------------------------ */
/* Public website                                                      */
/* ------------------------------------------------------------------ */
$router->group(['middleware' => ['Installed']], function ($r): void {
    $r->get('/', 'Public_\HomeController@index');
    $r->get('/services', 'Public_\HomeController@services');
    $r->get('/services/{slug}', 'Public_\HomeController@service');
    $r->get('/team', 'Public_\HomeController@team');
    $r->get('/gallery', 'Public_\HomeController@gallery');
    $r->get('/blog', 'Public_\HomeController@blog');
    $r->get('/blog/{slug}', 'Public_\HomeController@post');
    $r->get('/contact', 'Public_\HomeController@contact');
    $r->post('/contact', 'Public_\HomeController@submitContact')->middleware(['Csrf', 'RateLimit:contact,5,60']);
    $r->get('/booking', 'Public_\BookingController@index');
    $r->post('/booking/slots', 'Public_\BookingController@slots')->middleware(['Csrf', 'RateLimit:slots,60,60']);
    $r->post('/booking', 'Public_\BookingController@store')->middleware(['Csrf', 'RateLimit:booking,10,60']);
    $r->get('/booking/success/{code}', 'Public_\BookingController@success');
    $r->get('/sitemap.xml', 'Public_\SeoController@sitemap');
    $r->get('/robots.txt', 'Public_\SeoController@robots');
});

/* ------------------------------------------------------------------ */
/* Authentication                                                      */
/* ------------------------------------------------------------------ */
$router->group(['middleware' => ['Installed']], function ($r): void {
    $r->get('/login', 'AuthController@showLogin')->middleware('Guest');
    $r->post('/login', 'AuthController@login')->middleware(['Guest', 'Csrf', 'RateLimit:login,10,300']);
    $r->post('/logout', 'AuthController@logout')->middleware(['Auth', 'Csrf']);
    $r->get('/forgot-password', 'AuthController@showForgot')->middleware('Guest');
    $r->post('/forgot-password', 'AuthController@sendReset')->middleware(['Guest', 'Csrf', 'RateLimit:forgot,5,900']);
    $r->get('/reset-password/{token}', 'AuthController@showReset')->middleware('Guest');
    $r->post('/reset-password', 'AuthController@resetPassword')->middleware(['Guest', 'Csrf']);
    $r->get('/profile', 'AuthController@showProfile')->middleware('Auth');
    $r->post('/profile/password', 'AuthController@updatePassword')->middleware(['Auth', 'Csrf']);
});

/* ------------------------------------------------------------------ */
/* Admin panel                                                         */
/* ------------------------------------------------------------------ */
$router->group([
    'prefix'     => '/admin',
    'middleware' => ['Installed', 'Auth', 'Role:SUPER_ADMIN,ADMIN,BRANCH_MANAGER,RECEPTION'],
], function ($r): void {
    $r->get('/', 'Admin\DashboardController@index');
    $r->get('/dashboard/charts', 'Admin\DashboardController@charts');

    /* Honest "coming soon" placeholder for menu items with no backend yet. */
    $r->get('/soon/{slug}', 'Admin\ComingSoonController@show');

    /* Customers */
    $r->get('/customers', 'Admin\CustomerController@index');
    $r->get('/customers/create', 'Admin\CustomerController@create');
    $r->post('/customers', 'Admin\CustomerController@store')->middleware('Csrf');
    $r->get('/customers/search', 'Admin\CustomerController@search');
    $r->get('/customers/export', 'Admin\CustomerController@export');
    $r->get('/customers/{id}', 'Admin\CustomerController@show');
    $r->get('/customers/{id}/edit', 'Admin\CustomerController@edit');
    $r->post('/customers/{id}', 'Admin\CustomerController@update')->middleware('Csrf');
    $r->post('/customers/{id}/delete', 'Admin\CustomerController@destroy')->middleware('Csrf');
    $r->post('/customers/{id}/notes', 'Admin\CustomerController@addNote')->middleware('Csrf');
    $r->post('/customers/{id}/loyalty', 'Admin\CustomerController@adjustLoyalty')->middleware('Csrf');
    $r->post('/customers/{id}/wallet', 'Admin\CustomerController@walletTopUp')->middleware('Csrf');

    /* Appointments */
    $r->get('/appointments', 'Admin\AppointmentController@index');
    $r->get('/appointments/calendar', 'Admin\AppointmentController@calendar');
    $r->get('/appointments/calendar/feed', 'Admin\AppointmentController@calendarFeed');
    $r->get('/appointments/slots', 'Admin\AppointmentController@slots');
    $r->get('/appointments/export', 'Admin\AppointmentController@export');
    $r->get('/appointments/create', 'Admin\AppointmentController@create');
    $r->post('/appointments', 'Admin\AppointmentController@store')->middleware('Csrf');
    $r->get('/appointments/{id}', 'Admin\AppointmentController@show');
    $r->post('/appointments/{id}/status', 'Admin\AppointmentController@changeStatus')->middleware('Csrf');
    $r->post('/appointments/{id}/cancel', 'Admin\AppointmentController@cancel')->middleware('Csrf');
    $r->post('/appointments/{id}/reschedule', 'Admin\AppointmentController@reschedule')->middleware('Csrf');

    /* Services */
    $r->get('/services', 'Admin\ServiceController@index');
    $r->get('/services/create', 'Admin\ServiceController@create');
    $r->post('/services', 'Admin\ServiceController@store')->middleware('Csrf');
    $r->get('/services/categories', 'Admin\ServiceController@categories');
    $r->post('/services/categories', 'Admin\ServiceController@storeCategory')->middleware('Csrf');
    $r->post('/services/categories/{id}', 'Admin\ServiceController@updateCategory')->middleware('Csrf');
    $r->post('/services/categories/{id}/delete', 'Admin\ServiceController@destroyCategory')->middleware('Csrf');
    $r->get('/services/{id}/edit', 'Admin\ServiceController@edit');
    $r->post('/services/{id}', 'Admin\ServiceController@update')->middleware('Csrf');
    $r->post('/services/{id}/toggle', 'Admin\ServiceController@toggleStatus')->middleware('Csrf');
    $r->post('/services/{id}/delete', 'Admin\ServiceController@destroy')->middleware('Csrf');

    /* Staff */
    $r->get('/staff', 'Admin\StaffController@index');
    $r->get('/staff/create', 'Admin\StaffController@create');
    $r->post('/staff', 'Admin\StaffController@store')->middleware('Csrf');
    $r->get('/staff/{id}', 'Admin\StaffController@show');
    $r->get('/staff/{id}/edit', 'Admin\StaffController@edit');
    $r->post('/staff/{id}', 'Admin\StaffController@update')->middleware('Csrf');
    $r->post('/staff/{id}/delete', 'Admin\StaffController@destroy')->middleware('Csrf');
    $r->post('/staff/{id}/shifts', 'Admin\StaffController@saveShiftsAction')->middleware('Csrf');
    $r->post('/staff/{id}/leaves', 'Admin\StaffController@storeLeave')->middleware('Csrf');

    /* Staff profile — live forms (آفر در لحظه / سفر مشتری / مدیریت مشتریان / ارزیابی عملکرد) */
    $r->post('/staff/{id}/instant-offers', 'Admin\StaffController@storeInstantOffer')->middleware('Csrf');
    $r->post('/staff/{id}/instant-offers/{offerId}/delete', 'Admin\StaffController@deleteInstantOffer')->middleware('Csrf');
    $r->post('/staff/{id}/journey', 'Admin\StaffController@storeJourneyEntry')->middleware('Csrf');
    $r->post('/staff/{id}/journey/{entryId}/delete', 'Admin\StaffController@deleteJourneyEntry')->middleware('Csrf');
    $r->post('/staff/{id}/customer-reports', 'Admin\StaffController@storeCustomerReport')->middleware('Csrf');
    $r->post('/staff/{id}/customer-reports/{reportId}/delete', 'Admin\StaffController@deleteCustomerReport')->middleware('Csrf');
    $r->post('/staff/{id}/evaluations', 'Admin\StaffController@storeEvaluation')->middleware('Csrf');

    /* Finance / POS */
    $r->get('/pos', 'Admin\InvoiceController@pos');
    $r->post('/pos/quote', 'Admin\InvoiceController@quote')->middleware('Csrf');
    $r->get('/invoices', 'Admin\InvoiceController@index');
    $r->get('/invoices/export', 'Admin\InvoiceController@export');
    $r->post('/invoices', 'Admin\InvoiceController@store')->middleware('Csrf');
    $r->get('/invoices/{id}', 'Admin\InvoiceController@show');
    $r->get('/invoices/{id}/print', 'Admin\InvoiceController@print');
    $r->post('/invoices/{id}/pay', 'Admin\InvoiceController@pay')->middleware('Csrf');
    $r->post('/invoices/{id}/refund', 'Admin\InvoiceController@refund')->middleware('Csrf');
    $r->post('/invoices/{id}/cancel', 'Admin\InvoiceController@cancel')->middleware('Csrf');

    /* Notifications */
    $r->get('/notifications', 'Admin\NotificationController@index');
    $r->post('/notifications/{id}/read', 'Admin\NotificationController@read')->middleware('Csrf');
    $r->post('/notifications/read-all', 'Admin\NotificationController@readAll')->middleware('Csrf');
    $r->post('/notifications/{id}/archive', 'Admin\NotificationController@archive')->middleware('Csrf');
});

/* Admin areas restricted to managers and above. */
$router->group([
    'prefix'     => '/admin',
    'middleware' => ['Installed', 'Auth', 'Role:SUPER_ADMIN,ADMIN,BRANCH_MANAGER'],
], function ($r): void {
    /* Inventory */
    $r->get('/inventory', 'Admin\InventoryController@index');
    $r->get('/inventory/products', 'Admin\InventoryController@products');
    $r->get('/inventory/products/create', 'Admin\InventoryController@createProduct');
    $r->post('/inventory/products', 'Admin\InventoryController@storeProduct')->middleware('Csrf');
    $r->get('/inventory/products/{id}/edit', 'Admin\InventoryController@editProduct');
    $r->post('/inventory/products/{id}', 'Admin\InventoryController@updateProduct')->middleware('Csrf');
    $r->post('/inventory/adjust', 'Admin\InventoryController@adjust')->middleware('Csrf');
    $r->get('/inventory/transactions', 'Admin\InventoryController@transactions');

    /* Marketing */
    $r->get('/marketing/campaigns', 'Admin\MarketingController@campaigns');
    $r->post('/marketing/campaigns', 'Admin\MarketingController@storeCampaign')->middleware('Csrf');
    $r->post('/marketing/campaigns/{id}/send', 'Admin\MarketingController@sendCampaign')->middleware('Csrf');
    $r->get('/marketing/automations', 'Admin\MarketingController@automations');
    $r->post('/marketing/automations', 'Admin\MarketingController@storeAutomation')->middleware('Csrf');
    $r->post('/marketing/automations/{id}/toggle', 'Admin\MarketingController@toggleAutomation')->middleware('Csrf');
    $r->get('/marketing/discounts', 'Admin\MarketingController@discounts');
    $r->post('/marketing/discounts', 'Admin\MarketingController@storeDiscount')->middleware('Csrf');

    /* Reports */
    $r->get('/reports', 'Admin\ReportController@index');
    $r->get('/reports/sales', 'Admin\ReportController@sales');
    $r->get('/reports/appointments', 'Admin\ReportController@appointments');
    $r->get('/reports/staff', 'Admin\ReportController@staff');
    $r->get('/reports/customers', 'Admin\ReportController@customers');
    $r->get('/reports/inventory', 'Admin\ReportController@inventory');
    $r->get('/reports/{report}/export', 'Admin\ReportController@export');

    /* Import */
    $r->get('/import', 'Admin\ImportController@index');
    $r->get('/import/template/{entity}', 'Admin\ImportController@template');
    $r->post('/import/preview', 'Admin\ImportController@preview')->middleware('Csrf');
    $r->post('/import/commit', 'Admin\ImportController@commit')->middleware('Csrf');
    $r->get('/import/errors', 'Admin\ImportController@errors');
});

/* Settings and system tools: admins only. */
$router->group([
    'prefix'     => '/admin',
    'middleware' => ['Installed', 'Auth', 'Role:SUPER_ADMIN,ADMIN'],
], function ($r): void {
    $r->get('/settings', 'Admin\SettingController@index');
    $r->post('/settings', 'Admin\SettingController@save')->middleware('Csrf');
    $r->get('/settings/branches', 'Admin\SettingController@branches');
    $r->post('/settings/branches', 'Admin\SettingController@saveBranch')->middleware('Csrf');
    $r->get('/settings/users', 'Admin\SettingController@users');
    $r->post('/settings/users/{id}/roles', 'Admin\SettingController@saveUserRoles')->middleware('Csrf');
    $r->get('/settings/backups', 'Admin\SettingController@backups');
    $r->post('/settings/backups', 'Admin\SettingController@createBackup')->middleware('Csrf');
    $r->get('/settings/backups/{file}/download', 'Admin\SettingController@downloadBackup');
    $r->post('/settings/backups/{file}/delete', 'Admin\SettingController@deleteBackup')->middleware('Csrf');
    $r->get('/settings/audit', 'Admin\SettingController@audit');
    $r->get('/settings/documents', 'Admin\SettingController@documents');
    $r->post('/settings/documents', 'Admin\SettingController@uploadDocument')->middleware('Csrf');
    $r->post('/settings/documents/{id}/delete', 'Admin\SettingController@deleteDocument')->middleware('Csrf');
    $r->get('/settings/documents/{id}/download', 'Admin\SettingController@downloadDocument');
});

/* ------------------------------------------------------------------ */
/* Staff portal                                                        */
/* ------------------------------------------------------------------ */
$router->group([
    'prefix'     => '/staff',
    'middleware' => ['Installed', 'Auth', 'Role:SPECIALIST,BRANCH_MANAGER,ADMIN,SUPER_ADMIN'],
], function ($r): void {
    $r->get('/', 'Staff\PortalController@index');
    $r->get('/schedule', 'Staff\PortalController@schedule');
    $r->get('/appointments', 'Staff\PortalController@appointments');
    $r->post('/appointments/{id}/status', 'Staff\PortalController@changeStatus')->middleware('Csrf');
    $r->get('/commissions', 'Staff\PortalController@commissions');
    $r->get('/performance', 'Staff\PortalController@performance');
    $r->get('/customers/{id}', 'Staff\PortalController@customer');
});

/* ------------------------------------------------------------------ */
/* Customer portal                                                     */
/* ------------------------------------------------------------------ */
$router->group([
    'prefix'     => '/customer',
    'middleware' => ['Installed', 'Auth', 'Role:CUSTOMER'],
], function ($r): void {
    $r->get('/', 'Customer\PortalController@index');
    $r->get('/appointments', 'Customer\PortalController@appointments');
    $r->post('/appointments/{id}/cancel', 'Customer\PortalController@cancel')->middleware('Csrf');
    $r->get('/invoices', 'Customer\PortalController@invoices');
    $r->get('/invoices/{id}', 'Customer\PortalController@invoice');
    $r->get('/loyalty', 'Customer\PortalController@loyalty');
    $r->get('/wallet', 'Customer\PortalController@wallet');
    $r->get('/profile', 'Customer\PortalController@profile');
    $r->post('/profile', 'Customer\PortalController@updateProfile')->middleware('Csrf');
    $r->get('/reviews', 'Customer\PortalController@reviews');
    $r->post('/reviews', 'Customer\PortalController@storeReview')->middleware('Csrf');
});

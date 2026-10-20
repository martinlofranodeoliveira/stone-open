<?php
declare(strict_types=1);
// Front controller
// Only assets should live under /public besides this entrypoint

require __DIR__ . '/../src/bootstrap.php';

use App\Infrastructure\Http\{Request, Response, Router};
use App\Controllers\{PaymentsController, WebhooksController, HealthController, CardsController, DocsController};
use App\Controllers\{TasksController};
use App\Controllers\Api\{SubscriptionsController as ApiSubscriptionsController, QueriesController as ApiQueriesController, CustomersController as ApiCustomersController};
use App\Controllers\Api\{PaymentLinksController as ApiPaymentLinksController};
use App\Controllers\Api\{PlansController as ApiPlansController};
use App\Controllers\Admin\{AdminAuthController, AdminDashboardController, AdminSitesController, AdminWebhooksController, AdminPaymentsController, AdminCustomersController, AdminSubscriptionsController, AdminPlansController, AdminInvoicesController};
use App\Controllers\Admin\AdminReportsController;
use App\Controllers\Admin\{AdminForwardLogsController, AdminUsersController, AdminAccessLogsController, AdminMetricsController, AdminHelpController, AdminAccountController, AdminApiLogsController, AdminPagarmeAccountsController};
use App\Controllers\Admin\AdminDiagnosticsController;
use App\Controllers\Admin\AdminUtilsController;
use App\Controllers\Admin\{AdminPaymentLinksController};
use App\Controllers\Admin\AdminVerifyController;
use App\Infrastructure\Http\Middleware\{JsonBodyParserMiddleware, AuthMiddleware, RateLimitMiddleware, ApiRequestLoggerMiddleware, RequestIdMiddleware};
use App\Infrastructure\Http\Middleware\{AdminSessionMiddleware, AdminAuthMiddleware, AdminActivityLoggerMiddleware, SecurityHeadersMiddleware};
use App\Support\Env;
use App\Helpers\Helper;

$request = Request::fromGlobals();

$router = new Router();

// Public route: health
$router->map('GET', '/', [HealthController::class, 'index'], [RequestIdMiddleware::class]);

// Public route: API docs (Swagger UI + JSON)
$router->map('GET', '/api/openapi.json', [DocsController::class, 'openapi'], [RequestIdMiddleware::class]);
$router->map('GET', '/api/docs', [DocsController::class, 'ui'], [RequestIdMiddleware::class]);

// Public route: Stone webhooks
$router->map('POST', '/webhooks/stone', [WebhooksController::class, 'handle'], [RequestIdMiddleware::class]);

// Public route: card tokenization (Pagar.me v5)
$router->map('POST', '/api/v1/cards/tokenize', [CardsController::class, 'tokenize'], [RequestIdMiddleware::class, JsonBodyParserMiddleware::class]);

// Internal task endpoints (HTTP worker) — protect with TASKS_SECRET
$router->map('GET', '/tasks/drain', [TasksController::class, 'drain'], [RequestIdMiddleware::class]);
$router->map('GET', '/tasks/retry', [TasksController::class, 'retry'], [RequestIdMiddleware::class]);
$router->map('GET', '/tasks/cleanup', [TasksController::class, 'cleanup'], [RequestIdMiddleware::class]);

// Private routes: require Auth + JSON parser + Rate limit
$mwApi = [RequestIdMiddleware::class, ApiRequestLoggerMiddleware::class, AuthMiddleware::class, RateLimitMiddleware::class];
$mwApiJson = [RequestIdMiddleware::class, ApiRequestLoggerMiddleware::class, JsonBodyParserMiddleware::class, AuthMiddleware::class, RateLimitMiddleware::class];
$mwApiJsonNoLog = [RequestIdMiddleware::class, JsonBodyParserMiddleware::class, AuthMiddleware::class, RateLimitMiddleware::class];
$router->map('POST', '/api/v1/payments', [PaymentsController::class, 'create'], $mwApiJson);
$router->map('POST', '/api/v1/payments/card', [PaymentsController::class, 'createWithCardData'], $mwApiJsonNoLog);
$router->map('POST', '/api/v1/boletos', [\App\Controllers\Api\BoletosController::class, 'create'], $mwApiJson);
$router->map('POST', '/api/v1/payments/for-customer', [PaymentsController::class, 'createForCustomer'], $mwApiJson);

// Private: subscriptions (API)
$router->map('POST', '/api/v1/subscriptions', [ApiSubscriptionsController::class, 'create'], $mwApiJson);
$router->map('POST', '/api/v1/subscriptions/for-customer', [ApiSubscriptionsController::class, 'createForCustomer'], $mwApiJson);

// Private: payment links (API)
$router->map('POST', '/api/v1/payment-links', [ApiPaymentLinksController::class, 'create'], $mwApiJson);

// Private: create subscription plans (API)
$router->map('POST', '/api/v1/plans', [ApiPlansController::class, 'create'], $mwApiJson);
// Private: list subscription plans (API)
$router->map('GET', '/api/v1/plans', [ApiPlansController::class, 'index'], $mwApi);

// Combined helpers
$router->map('POST', '/api/v1/customers/upsert', [ApiCustomersController::class, 'upsert'], $mwApiJson);
$router->map('POST', '/api/v1/customers/update', [ApiCustomersController::class, 'updateByLookup'], $mwApiJson);
$router->map('PUT', '/api/v1/customers/{id}', [ApiCustomersController::class, 'update'], $mwApiJson);

// Private: queries
$router->map('GET', '/api/v1/payments/{id}', [ApiQueriesController::class, 'paymentById'], $mwApi);
$router->map('GET', '/api/v1/payments/stone/{sid}', [ApiQueriesController::class, 'paymentByStoneId'], $mwApi);
$router->map('GET', '/api/v1/payments/by-ref/{ref}', [ApiQueriesController::class, 'paymentByRef'], $mwApi);
$router->map('GET', '/api/v1/payments/by-ref/{ref}/history', [ApiQueriesController::class, 'paymentsByRefHistory'], $mwApi);
$router->map('GET', '/api/v1/payments', [ApiQueriesController::class, 'paymentsSearch'], $mwApi);
$router->map('GET', '/api/v1/boletos/{id}', [\App\Controllers\Api\BoletosController::class, 'show'], $mwApi);
$router->map('GET', '/api/v1/boletos/stone/{sid}', [\App\Controllers\Api\BoletosController::class, 'showByStone'], $mwApi);
$router->map('GET', '/api/v1/boletos/remote', [\App\Controllers\Api\BoletosController::class, 'remote'], $mwApi);
$router->map('GET', '/api/v1/boletos', [\App\Controllers\Api\BoletosController::class, 'index'], $mwApi);
$router->map('GET', '/api/v1/customers/{id}', [ApiQueriesController::class, 'customerById'], $mwApi);
$router->map('GET', '/api/v1/customers', [ApiQueriesController::class, 'customersSearch'], $mwApi);
$router->map('GET', '/api/v1/customers/search', [ApiCustomersController::class, 'search'], $mwApi);
$router->map('GET', '/api/v1/subscriptions/{id}', [ApiSubscriptionsController::class, 'show'], $mwApi);
$router->map('GET', '/api/v1/subscriptions/stone/{sid}', [ApiSubscriptionsController::class, 'showByStone'], $mwApi);
$router->map('GET', '/api/v1/subscriptions/{id}/events', [ApiSubscriptionsController::class, 'events'], $mwApi);
$router->map('GET', '/api/v1/payments/by-subscription/{sid}', [ApiQueriesController::class, 'paymentsBySubscription'], $mwApi);

// Private: card brand + installments (one-shot quote)
$router->map('GET', '/api/v1/cards/quote', [CardsController::class, 'quote'], $mwApi);

// Private: single installment quote (N)
$router->map('GET', '/api/v1/cards/quote/installment', [CardsController::class, 'quoteInstallment'], $mwApi);

// Optional: only brand lookup
$router->map('GET', '/api/v1/cards/brand', [CardsController::class, 'brand'], $mwApi);

// Admin routes (hidden under configurable prefix)
$adminPrefix = rtrim(Env::get('ADMIN_PREFIX', '/_admin'), '/');

// Login/logout
$router->map('GET', $adminPrefix . '/login', [AdminAuthController::class, 'loginForm'], [
    RequestIdMiddleware::class,
    AdminSessionMiddleware::class,
    SecurityHeadersMiddleware::class,
]);
$router->map('GET', $adminPrefix . '/captcha', [AdminAuthController::class, 'captcha'], [
    RequestIdMiddleware::class,
    AdminSessionMiddleware::class,
    SecurityHeadersMiddleware::class,
]);
$router->map('POST', $adminPrefix . '/login', [AdminAuthController::class, 'login'], [
    RequestIdMiddleware::class,
    AdminSessionMiddleware::class,
    SecurityHeadersMiddleware::class,
]);
$router->map('GET', $adminPrefix . '/logout', [AdminAuthController::class, 'logout'], [
    RequestIdMiddleware::class,
    AdminSessionMiddleware::class,
    SecurityHeadersMiddleware::class,
]);

// Protected admin
$mwAdmin = [RequestIdMiddleware::class, AdminSessionMiddleware::class, AdminAuthMiddleware::class, AdminActivityLoggerMiddleware::class, SecurityHeadersMiddleware::class];
$router->map('GET', $adminPrefix, [AdminDashboardController::class, 'index'], $mwAdmin);

$router->map('GET', $adminPrefix . '/sites', [AdminSitesController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/sites/data', [AdminSitesController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/sites/create', [AdminSitesController::class, 'createForm'], $mwAdmin);
$router->map('GET', $adminPrefix . '/sites/{id}', [AdminSitesController::class, 'editForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/sites/store', [AdminSitesController::class, 'store'], $mwAdmin);
$router->map('POST', $adminPrefix . '/sites/update', [AdminSitesController::class, 'update'], $mwAdmin);
$router->map('GET', $adminPrefix . '/sites/{id}/quote-preview', [AdminSitesController::class, 'quotePreview'], $mwAdmin);
$router->map('POST', $adminPrefix . '/sites/reset-secrets', [AdminSitesController::class, 'resetSecrets'], $mwAdmin);
$router->map('POST', $adminPrefix . '/sites/rotate-token', [AdminSitesController::class, 'rotateApiToken'], $mwAdmin);
$router->map('POST', $adminPrefix . '/sites/toggle', [AdminSitesController::class, 'toggleStatus'], $mwAdmin);
$router->map('POST', $adminPrefix . '/sites/delete', [AdminSitesController::class, 'delete'], $mwAdmin);

$router->map('GET', $adminPrefix . '/payments', [AdminPaymentsController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payments/export', [AdminPaymentsController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payments/export-xls', [AdminPaymentsController::class, 'exportXls'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payments/data', [AdminPaymentsController::class, 'data'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/capture', [AdminPaymentsController::class, 'capture'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/retry', [AdminPaymentsController::class, 'retry'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/cancel', [AdminPaymentsController::class, 'cancel'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/refund', [AdminPaymentsController::class, 'refund'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/force-settle', [AdminPaymentsController::class, 'forceSettle'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/resend', [AdminPaymentsController::class, 'resend'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/resend-batch', [AdminPaymentsController::class, 'resendBatch'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payments/json', [AdminPaymentsController::class, 'json'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payments/delete', [AdminPaymentsController::class, 'delete'], $mwAdmin);
// Payment history (status changes)
$router->map('GET', $adminPrefix . '/payments/{id}/history', [AdminPaymentsController::class, 'history'], $mwAdmin);
$router->map('GET', $adminPrefix . '/customers', [AdminCustomersController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/customers/export', [AdminCustomersController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/customers/export-xls', [AdminCustomersController::class, 'exportXls'], $mwAdmin);
$router->map('GET', $adminPrefix . '/customers/data', [AdminCustomersController::class, 'data'], $mwAdmin);

$router->map('GET', $adminPrefix . '/webhooks', [AdminWebhooksController::class, 'list'], $mwAdmin);
$router->map('POST', $adminPrefix . '/webhooks/resend', [AdminWebhooksController::class, 'resend'], $mwAdmin);
$router->map('GET', $adminPrefix . '/webhooks/json', [AdminWebhooksController::class, 'json'], $mwAdmin);
$router->map('POST', $adminPrefix . '/webhooks/delete', [AdminWebhooksController::class, 'delete'], $mwAdmin);

// Account settings
$router->map('GET', $adminPrefix . '/account', [AdminAccountController::class, 'edit'], $mwAdmin);
$router->map('POST', $adminPrefix . '/account/save', [AdminAccountController::class, 'save'], $mwAdmin);
$router->map('GET', $adminPrefix . '/webhooks/export', [AdminWebhooksController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/webhooks/export-xls', [AdminWebhooksController::class, 'exportXls'], $mwAdmin);
$router->map('GET', $adminPrefix . '/webhooks/data', [AdminWebhooksController::class, 'data'], $mwAdmin);
// Lookup inbound by provider_event_id (for admin tools)
$router->map('GET', $adminPrefix . '/webhooks/lookup', [AdminWebhooksController::class, 'lookupByProviderEvent'], $mwAdmin);

$router->map('GET', $adminPrefix . '/forward-logs', [AdminForwardLogsController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/forward-logs/export', [AdminForwardLogsController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/forward-logs/export-xls', [AdminForwardLogsController::class, 'exportXls'], $mwAdmin);
$router->map('GET', $adminPrefix . '/forward-logs/data', [AdminForwardLogsController::class, 'data'], $mwAdmin);

$router->map('GET', $adminPrefix . '/payment-links', [AdminPaymentLinksController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payment-links/data', [AdminPaymentLinksController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payment-links/export', [AdminPaymentLinksController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payment-links/export-xls', [AdminPaymentLinksController::class, 'exportXls'], $mwAdmin);
$router->map('GET', $adminPrefix . '/payment-links/json', [AdminPaymentLinksController::class, 'json'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payment-links/delete', [AdminPaymentLinksController::class, 'delete'], $mwAdmin);
$router->map('POST', $adminPrefix . '/payment-links/resend', [AdminPaymentLinksController::class, 'resend'], $mwAdmin);

$router->map('GET', $adminPrefix . '/invoices', [AdminInvoicesController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/invoices/data', [AdminInvoicesController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/invoices/export', [AdminInvoicesController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/invoices/export-xls', [AdminInvoicesController::class, 'exportXls'], $mwAdmin);

$router->map('GET', $adminPrefix . '/subscriptions', [AdminSubscriptionsController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/subscriptions/export', [AdminSubscriptionsController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/subscriptions/export-xls', [AdminSubscriptionsController::class, 'exportXls'], $mwAdmin);
$router->map('GET', $adminPrefix . '/subscriptions/data', [AdminSubscriptionsController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/subscriptions/{id}/events', [AdminSubscriptionsController::class, 'events'], $mwAdmin);
$router->map('GET', $adminPrefix . '/subscriptions/{id}/plan', [AdminSubscriptionsController::class, 'planInfo'], $mwAdmin);
$router->map('POST', $adminPrefix . '/subscriptions/forward-payment', [AdminSubscriptionsController::class, 'forwardPayment'], $mwAdmin);
$router->map('POST', $adminPrefix . '/subscriptions/{id}/cancel', [AdminSubscriptionsController::class, 'cancel'], $mwAdmin);
$router->map('POST', $adminPrefix . '/subscriptions/{id}/pause', [AdminSubscriptionsController::class, 'pause'], $mwAdmin);
$router->map('POST', $adminPrefix . '/subscriptions/{id}/resume', [AdminSubscriptionsController::class, 'resume'], $mwAdmin);
$router->map('POST', $adminPrefix . '/subscriptions/{id}/emit-event', [AdminSubscriptionsController::class, 'emitEvent'], $mwAdmin);
$router->map('GET', $adminPrefix . '/subscriptions/json', [AdminSubscriptionsController::class, 'json'], $mwAdmin);
$router->map('POST', $adminPrefix . '/subscriptions/delete', [AdminSubscriptionsController::class, 'delete'], $mwAdmin);

$router->map('GET', $adminPrefix . '/plans', [AdminPlansController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/plans/data', [AdminPlansController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/plans/create', [AdminPlansController::class, 'createForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/plans/store', [AdminPlansController::class, 'store'], $mwAdmin);
$router->map('GET', $adminPrefix . '/plans/{id}', [AdminPlansController::class, 'editForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/plans/update', [AdminPlansController::class, 'update'], $mwAdmin);
$router->map('POST', $adminPrefix . '/plans/toggle', [AdminPlansController::class, 'toggle'], $mwAdmin);
$router->map('POST', $adminPrefix . '/plans/push', [AdminPlansController::class, 'pushToStone'], $mwAdmin);

// Admin users
$router->map('GET', $adminPrefix . '/users', [AdminUsersController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/users/create', [AdminUsersController::class, 'createForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/users/store', [AdminUsersController::class, 'store'], $mwAdmin);
$router->map('GET', $adminPrefix . '/users/{id}', [AdminUsersController::class, 'editForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/users/update', [AdminUsersController::class, 'update'], $mwAdmin);
$router->map('POST', $adminPrefix . '/users/toggle', [AdminUsersController::class, 'toggle'], $mwAdmin);
$router->map('POST', $adminPrefix . '/users/2fa/enable', [AdminUsersController::class, 'enable2fa'], $mwAdmin);
$router->map('POST', $adminPrefix . '/users/2fa/verify', [AdminUsersController::class, 'verify2fa'], $mwAdmin);
$router->map('POST', $adminPrefix . '/users/2fa/disable', [AdminUsersController::class, 'disable2fa'], $mwAdmin);

// Admin access logs
$router->map('GET', $adminPrefix . '/access-logs', [AdminAccessLogsController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/access-logs/data', [AdminAccessLogsController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/access-logs/export', [AdminAccessLogsController::class, 'exportCsv'], $mwAdmin);

// Admin metrics (Prometheus text)
$router->map('GET', $adminPrefix . '/metrics', [AdminMetricsController::class, 'index'], $mwAdmin);

// Admin help/docs
$router->map('GET', $adminPrefix . '/help', [AdminHelpController::class, 'index'], $mwAdmin);
$router->map('GET', $adminPrefix . '/help/client-php', [AdminHelpController::class, 'downloadClient'], $mwAdmin);
$router->map('GET', $adminPrefix . '/help/usage', [AdminHelpController::class, 'downloadUsage'], $mwAdmin);
$router->map('GET', $adminPrefix . '/help/usage-html', [AdminHelpController::class, 'downloadUsageHtml'], $mwAdmin);
$router->map('GET', $adminPrefix . '/help/postman', [AdminHelpController::class, 'downloadPostman'], $mwAdmin);

// Admin pagarme accounts
$router->map('GET', $adminPrefix . '/pagarme-accounts', [AdminPagarmeAccountsController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/pagarme-accounts/create', [AdminPagarmeAccountsController::class, 'createForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/pagarme-accounts/store', [AdminPagarmeAccountsController::class, 'store'], $mwAdmin);
$router->map('GET', $adminPrefix . '/pagarme-accounts/{id}', [AdminPagarmeAccountsController::class, 'editForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/pagarme-accounts/update', [AdminPagarmeAccountsController::class, 'update'], $mwAdmin);
$router->map('POST', $adminPrefix . '/pagarme-accounts/delete', [AdminPagarmeAccountsController::class, 'delete'], $mwAdmin);
$router->map('POST', $adminPrefix . '/pagarme-accounts/toggle-env', [AdminPagarmeAccountsController::class, 'toggleEnv'], $mwAdmin);

// Admin API request logs
$router->map('GET', $adminPrefix . '/api-logs', [AdminApiLogsController::class, 'list'], $mwAdmin);
$router->map('GET', $adminPrefix . '/api-logs/data', [AdminApiLogsController::class, 'data'], $mwAdmin);
$router->map('GET', $adminPrefix . '/api-logs/export', [AdminApiLogsController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/api-logs/export-xls', [AdminApiLogsController::class, 'exportXls'], $mwAdmin);

// Admin reports
$router->map('GET', $adminPrefix . '/reports', [AdminReportsController::class, 'index'], $mwAdmin);
$router->map('GET', $adminPrefix . '/reports/export', [AdminReportsController::class, 'exportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/reports/export-xls', [AdminReportsController::class, 'exportXls'], $mwAdmin);
// Reports by account
$router->map('GET', $adminPrefix . '/reports/accounts', [AdminReportsController::class, 'accounts'], $mwAdmin);
$router->map('GET', $adminPrefix . '/reports/accounts/export', [AdminReportsController::class, 'accountsExportCsv'], $mwAdmin);
$router->map('GET', $adminPrefix . '/reports/accounts/export-xls', [AdminReportsController::class, 'accountsExportXls'], $mwAdmin);

// Admin diagnostics
$router->map('GET', $adminPrefix . '/diagnostics/stone-auth', [AdminDiagnosticsController::class, 'stoneAuth'], $mwAdmin);

// Admin: Verificação direta na Stone
$router->map('GET', $adminPrefix . '/verify', [AdminVerifyController::class, 'index'], $mwAdmin);
$router->map('GET', $adminPrefix . '/verify/subscriptions', [AdminVerifyController::class, 'subscriptions'], $mwAdmin);
$router->map('GET', $adminPrefix . '/verify/payments', [AdminVerifyController::class, 'payments'], $mwAdmin);
$router->map('GET', $adminPrefix . '/verify/invoices', [AdminVerifyController::class, 'invoices'], $mwAdmin);

// Admin account: test auth (JSON)
$router->map('GET', $adminPrefix . '/account/test-auth', [AdminAccountController::class, 'testAuth'], $mwAdmin);

// Admin utils
$router->map('GET', $adminPrefix . '/calculadora', [AdminUtilsController::class, 'index'], $mwAdmin);
$router->map('GET', $adminPrefix . '/utils/cards/quote', [AdminUtilsController::class, 'cardsQuote'], $mwAdmin);
$router->map('GET', $adminPrefix . '/calculadora/cards/quote', [AdminUtilsController::class, 'cardsQuote'], $mwAdmin);
// Utils: payments helpers (PIX, card, payment link)
$router->map('GET', $adminPrefix . '/utils/pix', [AdminUtilsController::class, 'pixForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/utils/pix', [AdminUtilsController::class, 'pixCreate'], $mwAdmin);
$router->map('GET', $adminPrefix . '/utils/boleto', [AdminUtilsController::class, 'boletoForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/utils/boleto', [AdminUtilsController::class, 'boletoCreate'], $mwAdmin);
$router->map('GET', $adminPrefix . '/utils/card', [AdminUtilsController::class, 'cardForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/utils/card', [AdminUtilsController::class, 'cardCreate'], $mwAdmin);
$router->map('GET', $adminPrefix . '/utils/link', [AdminUtilsController::class, 'linkForm'], $mwAdmin);
$router->map('POST', $adminPrefix . '/utils/link', [AdminUtilsController::class, 'linkCreate'], $mwAdmin);

// Dispatch
$response = $router->dispatch($request);

Response::send($response);

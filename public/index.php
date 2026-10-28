<?php

/**
 * PHPSwapMeet 2 - Main Entry Point
 * Routes all requests through a single front controller
 */

declare(strict_types=1);

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Router;
use App\Response;
use App\Session;
use App\CSRF;
use App\Auth;
use App\Middleware\SecurityHeaders;
use App\Middleware\Authenticate;
use App\Middleware\EnsureAdmin;
use App\Middleware\RateLimit;
use App\Controllers\AuthController;
use App\Controllers\ItemController;
use App\Controllers\SearchController;
use App\Controllers\MessageController;
use App\Controllers\ProfileController;
use App\Controllers\AdminController;
use App\Controllers\RSSController;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Start session and CSRF
Session::start();
CSRF::start();

// Create router
$router = new Router();

// === Global middleware ===
$router->addMiddleware('/', SecurityHeaders::class);
$router->addMiddleware('/browse', SecurityHeaders::class);
$router->addMiddleware('/search', SecurityHeaders::class);
$router->addMiddleware('/login', SecurityHeaders::class);
$router->addMiddleware('/register', SecurityHeaders::class);
$router->addMiddleware('/forgot-password', SecurityHeaders::class);
$router->addMiddleware('/reset-password', SecurityHeaders::class);
$router->addMiddleware('/items', SecurityHeaders::class);
$router->addMiddleware('/items/create', SecurityHeaders::class);
$router->addMiddleware('/messages', SecurityHeaders::class);
$router->addMiddleware('/profile', SecurityHeaders::class);
$router->addMiddleware('/desktop', SecurityHeaders::class);
$router->addMiddleware('/admin', SecurityHeaders::class);

// === Public routes ===
$router->get('/', function () {
    $news = \App\Models\News::getActiveArticles();
    $items = \App\Models\Item::getRecentItems(6);
    (new \App\ResponseHandler('home.html.twig'))
        ->with('news', $news)
        ->with('items', $items)
        ->render();
});

$router->get('/browse', [ItemController::class, 'browse']);
$router->get('/items/{id}', [ItemController::class, 'show']);
$router->get('/search', [SearchController::class, 'index']);

// Auth routes
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->addMiddleware('/login', RateLimit::class);

$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->addMiddleware('/register', RateLimit::class);

$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->addMiddleware('/forgot-password', RateLimit::class);

$router->get('/reset-password', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// RSS feeds
$router->get('/rss/items', [RSSController::class, 'items']);
$router->get('/rss/news', [RSSController::class, 'news']);

// === Theme switcher ===
$router->get('/theme/switch', function () {
    $themeName = $_GET['theme'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';

    if ($themeName === '') {
        Session::flash('error', 'No theme specified.');
        Response::redirect($ref);
        return;
    }

    $theme = new \App\Theme\ThemeManager();
    if ($theme->setActive($themeName)) {
        Session::flash('success', 'Theme changed to ' . ucfirst(str_replace('_', ' ', $themeName)) . '.');
    } else {
        Session::flash('error', 'Invalid theme: ' . htmlspecialchars($themeName));
    }
    Response::redirect($ref);
});

// === Language switcher ===
$router->get('/language/switch', function () {
    $locale = $_GET['locale'] ?? '';
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';

    if ($locale === '' || !\App\Translation\TranslationManager::isSupported($locale)) {
        Response::redirect($ref);
        return;
    }

    // If logged in, save to database
    if (\App\Auth::isLoggedIn()) {
        \App\Models\User::update(\App\Auth::getId(), ['language' => $locale]);
    }

    // Save to cookie for guests
    setcookie('language', $locale, [
        'expires'  => time() + 365 * 86400,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    Response::redirect($ref);
});

// Theme list page (for testing)
$router->get('/themes', function () {
    $theme = new \App\Theme\ThemeManager();
    $themes = $theme->getAvailable();
    $active = $theme->getActive();

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Theme Selector</title>';
    $html .= '<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:0 20px;}';
    $html .= '.theme-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:20px;margin-top:20px;}';
    $html .= '.theme-card{border:1px solid #ddd;border-radius:8px;padding:20px;box-shadow:0 2px 4px rgba(0,0,0,0.05);}';
    $html .= '.theme-card h3{margin:0 0 8px;} .theme-card p{margin:0 0 12px;color:#666;font-size:13px;}';
    $html .= '.theme-card .active{background:#4CAF50;color:white;} .theme-card a{display:inline-block;padding:6px 16px;border-radius:4px;text-decoration:none;font-size:13px;}';
    $html .= '.theme-card a.btn{background:#2196F3;color:white;} .theme-card a.btn:hover{background:#1976D2;}';
    $html .= '.back{margin-bottom:20px;}</style></head><body>';
    $html .= '<a href="/" class="back">&larr; Back to site</a>';
    $html .= '<h1>Theme Selector</h1>';
    $html .= '<p>Choose a theme to preview. Your selection is saved in your session.</p>';
    $html .= '<div class="theme-grid">';
    foreach ($themes as $key => $info) {
        $isActive = $key === $active ? 'active' : '';
        $label = $isActive ? '✓ Active' : 'Switch';
        $btnStyle = $isActive ? 'background:#4CAF50;color:white;' : 'background:#2196F3;color:white;';
        $html .= '<div class="theme-card ' . $isActive . '">';
        $html .= '<h3>' . htmlspecialchars($info['name']) . '</h3>';
        $html .= '<p>' . htmlspecialchars($info['description']) . '</p>';
        $html .= '<p><small>by ' . htmlspecialchars($info['author']) . ' v' . htmlspecialchars($info['version']) . '</small></p>';
        $html .= '<a href="/theme/switch?theme=' . urlencode($key) . '" class="btn" style="' . $btnStyle . '">' . $label . '</a>';
        $html .= '</div>';
    }
    $html .= '</div></body></html>';
    echo $html;
});

// === Authenticated routes ===
$router->get('/desktop', function () {
    Auth::check();
    $userId = Auth::getId();
    $itemsResult = \App\Models\Item::getOwnerItems($userId, 1, 12);
    $unread = \App\Models\Message::getUnreadCount($userId);
    (new \App\ResponseHandler('desktop.html.twig'))
        ->with('items', $itemsResult['items'])
        ->with('item_pagination', [
            'page' => 1,
            'per_page' => 12,
            'total' => $itemsResult['total'],
            'base_url' => '/desktop',
        ])
        ->with('unread_count', $unread)
        ->render();
});

$router->addMiddleware('/items/create', Authenticate::class);
$router->get('/items/create', [ItemController::class, 'showCreate']);
$router->post('/items/create', [ItemController::class, 'create']);

$router->addMiddleware('/items/{id}/edit', Authenticate::class);
$router->get('/items/{id}/edit', [ItemController::class, 'showEdit']);
$router->post('/items/{id}/edit', [ItemController::class, 'update']);

$router->addMiddleware('/items/{id}/deactivate', Authenticate::class);
$router->get('/items/{id}/deactivate', [ItemController::class, 'deactivate']);

$router->addMiddleware('/items/{id}/activate', Authenticate::class);
$router->get('/items/{id}/activate', [ItemController::class, 'activate']);

// Messages
$router->addMiddleware('/messages/inbox', Authenticate::class);
$router->get('/messages/inbox', [MessageController::class, 'inbox']);

$router->addMiddleware('/messages/sent', Authenticate::class);
$router->get('/messages/sent', [MessageController::class, 'sent']);

$router->addMiddleware('/messages/trash', Authenticate::class);
$router->get('/messages/trash', [MessageController::class, 'trash']);
$router->get('/messages/empty-trash', [MessageController::class, 'emptyTrash']);

$router->addMiddleware('/messages/compose', Authenticate::class);
$router->get('/messages/compose', [MessageController::class, 'showCompose']);
$router->post('/messages/compose', [MessageController::class, 'compose']);

$router->addMiddleware('/messages/{id}', Authenticate::class);
$router->get('/messages/{id}', [MessageController::class, 'show']);

$router->addMiddleware('/messages/read/{id}', Authenticate::class);
$router->get('/messages/read/{id}', [MessageController::class, 'markRead']);

$router->addMiddleware('/messages/trash/{id}', Authenticate::class);
$router->get('/messages/trash/{id}', [MessageController::class, 'moveToTrash']);

$router->addMiddleware('/messages/permanently-delete/{id}', Authenticate::class);
$router->get('/messages/permanently-delete/{id}', [MessageController::class, 'permanentlyDelete']);

// Profile
$router->get('/profile/{id}', [ProfileController::class, 'show']);

$router->addMiddleware('/profile/edit', Authenticate::class);
$router->get('/profile/edit', [ProfileController::class, 'showEdit']);
$router->post('/profile/edit', [ProfileController::class, 'updateProfile']);

$router->addMiddleware('/profile/{id}/feedback', Authenticate::class);
$router->post('/profile/{id}/feedback', [ProfileController::class, 'submitFeedback']);

// === Admin routes ===
$router->addMiddleware('/admin', EnsureAdmin::class);
$router->get('/admin', [AdminController::class, 'dashboard']);

$router->addMiddleware('/admin/users', EnsureAdmin::class);
$router->get('/admin/users', [AdminController::class, 'manageUsers']);

$router->addMiddleware('/admin/users/{id}/toggle', EnsureAdmin::class);
$router->get('/admin/users/{id}/toggle', [AdminController::class, 'toggleUser']);

$router->addMiddleware('/admin/users/{id}/delete', EnsureAdmin::class);
$router->get('/admin/users/{id}/delete', [AdminController::class, 'deleteAccount']);

$router->addMiddleware('/admin/users/{id}/edit', EnsureAdmin::class);
$router->get('/admin/users/{id}/edit', [AdminController::class, 'editUser']);

$router->addMiddleware('/admin/users/{id}/update', EnsureAdmin::class);
$router->post('/admin/users/{id}/update', [AdminController::class, 'updateUser']);

$router->addMiddleware('/admin/categories', EnsureAdmin::class);
$router->get('/admin/categories', [AdminController::class, 'manageCategories']);
$router->post('/admin/categories/add', [AdminController::class, 'addCategory']);
$router->get('/admin/categories/{id}/toggle', [AdminController::class, 'toggleCategory']);

$router->addMiddleware('/admin/news', EnsureAdmin::class);
$router->get('/admin/news', [AdminController::class, 'manageNews']);
$router->get('/admin/news/new', [AdminController::class, 'showNewsForm']);
$router->get('/admin/news/{id}/edit', [AdminController::class, 'showNewsForm']);
$router->post('/admin/news/save', [AdminController::class, 'saveNews']);
$router->get('/admin/news/{id}/toggle', [AdminController::class, 'toggleNews']);
$router->get('/admin/news/{id}/delete', [AdminController::class, 'deleteNews']);

$router->addMiddleware('/admin/stats', EnsureAdmin::class);
$router->get('/admin/stats', [AdminController::class, 'stats']);

// Dispatch
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

try {
    $router->dispatch($method, $uri);
} catch (Throwable $e) {
    // Error handling
    http_response_code(500);
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

    if ($debug) {
        Response::html(
            '<h1>500 — Server Error</h1>' .
            '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>' .
            '<p>' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</p>' .
            '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>',
            500
        );
    } else {
        Response::html(
            '<h1>500 — Server Error</h1>' .
            '<p>Something went wrong. Please try again later.</p>',
            500
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Response;
use App\ResponseHandler;
use App\Session;
use App\CSRF;
use App\Validator;
use App\Database;
use App\Models\User;
use App\Models\Item;
use App\Models\Category;
use App\Models\News;
use App\Models\Feedback;
use App\Models\Message;

final class AdminController
{
    public function dashboard(): void
    {
        Auth::checkAdmin();

        $totalUsers = User::getCount();
        $activeUsers = User::getActiveCount();
        $totalItems = Item::getCount();
        $activeItems = Item::getActiveCount();
        $totalFeedback = Feedback::getTotalCount();
        $totalMessages = Message::getTotalCount();
        $recentUsers = User::getRecentUsers(10);
        $recentItems = Item::getRecentItems(10);

        (new ResponseHandler('admin/index.html.twig'))
            ->with('total_users', $totalUsers)
            ->with('active_users', $activeUsers)
            ->with('total_items', $totalItems)
            ->with('active_items', $activeItems)
            ->with('total_feedback', $totalFeedback)
            ->with('total_messages', $totalMessages)
            ->with('recent_users', $recentUsers)
            ->with('recent_items', $recentItems)
            ->render();
    }

    public function manageUsers(): void
    {
        Auth::checkAdmin();
        $users = User::getAll();

        (new ResponseHandler('admin/users.html.twig'))
            ->with('users', $users)
            ->render();
    }

    public function toggleUser(int $id): void
    {
        Auth::checkAdmin();
        $user = User::findById($id);
        if ($user === null) {
            Session::flash('error', 'User not found.');
            Response::redirect('/admin/users');
        }

        if ($id === Auth::getId()) {
            Session::flash('error', 'You cannot deactivate your own account.');
            Response::redirect('/admin/users');
        }

        User::toggleActive($id);
        Session::flash('success', 'User ' . ($user['is_active'] ? 'activated' : 'deactivated') . '.');
        Response::redirect('/admin/users');
    }

    /**
     * Show the admin user edit form.
     */
    public function editUser(int $id): void
    {
        Auth::checkAdmin();
        $user = User::findById($id);
        if ($user === null) {
            Session::flash('error', 'User not found.');
            Response::redirect('/admin/users');
        }

        (new ResponseHandler('admin/edit_user.html.twig'))
            ->with('user', $user)
            ->with('supported_locales', \App\Translation\TranslationManager::getSupportedLocales())
            ->render();
    }

    /**
     * Process admin user update.
     */
    public function updateUser(int $id): void
    {
        Auth::checkAdmin();
        $user = User::findById($id);
        if ($user === null) {
            Session::flash('error', 'User not found.');
            Response::redirect('/admin/users');
        }

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $validator = Validator::make($_POST)
            ->required('username')
            ->required('email')
            ->email('email')
            ->min('username', 3)
            ->max('username', 50)
            ->unique('username', 'users', 'username', $id);

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $emailValidator = Validator::make($_POST)
            ->unique('email', 'users', 'email', $id);

        if (!$emailValidator->validate()) {
            Session::flash('error', 'That email is already in use.');
            Session::flash('validation_errors', $emailValidator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/admin/users/' . $id . '/edit');
            return;
        }

        $updateData = [
            'username'    => $_POST['username'],
            'email'       => $_POST['email'],
            'paypal_email' => ($_POST['paypal_email'] !== '' ? $_POST['paypal_email'] : null),
            'first_name'  => $_POST['first_name'] ?? '',
            'last_name'   => $_POST['last_name'] ?? '',
            'bio'         => $_POST['bio'] ?? null,
        ];

        // Admin can change password (leave blank to keep current)
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 8) {
                Session::flash('error', 'Password must be at least 8 characters.');
                Session::flash('old', $_POST);
                Response::redirect('/admin/users/' . $id . '/edit');
                return;
            }
            $updateData['password_hash'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        // Admin can change active status — only if it actually changed
        $newActive = isset($_POST['is_active']) ? 1 : 0;
        if ($newActive !== (int) $user['is_active']) {
            if ($newActive) {
                User::activate($id);
            } else {
                User::deactivate($id);
            }
        }

        // Admin can change admin flag
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        if ($isAdmin !== (int) $user['is_admin']) {
            $stmt = Database::getConnection()->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->execute([$isAdmin, $id]);
        }

        // Update language
        $language = $_POST['language'] ?? 'en';
        if (\App\Translation\TranslationManager::isSupported($language)) {
            $updateData['language'] = $language;
        }

        User::update($id, $updateData);

        Session::flash('success', 'User updated successfully.');
        Response::redirect('/admin/users');
    }

    public function deleteAccount(int $id): void
    {
        Auth::checkAdmin();
        if ($id === Auth::getId()) {
            Session::flash('error', 'You cannot delete your own account.');
            Response::redirect('/admin/users');
        }

        User::softDelete($id);
        Session::flash('success', 'User account deleted.');
        Response::redirect('/admin/users');
    }

    public function manageCategories(): void
    {
        Auth::checkAdmin();
        $categories = Category::getAllWithCount();

        (new ResponseHandler('admin/categories.html.twig'))
            ->with('categories', $categories)
            ->render();
    }

    public function addCategory(): void
    {
        Auth::checkAdmin();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token.');
            Response::redirect('/admin/categories');
        }

        $validator = Validator::make($_POST)
            ->required('name')
            ->max('name', 100);

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/admin/categories');
            return;
        }

        Category::create([
            'name' => $_POST['name'],
            'description' => $_POST['description'] ?? null,
            'parent_id' => !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null,
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ]);

        Session::flash('success', 'Category added successfully.');
        Response::redirect('/admin/categories');
    }

    public function toggleCategory(int $id): void
    {
        Auth::checkAdmin();
        Category::toggleActive($id);
        Session::flash('success', 'Category updated.');
        Response::redirect('/admin/categories');
    }

    public function manageNews(): void
    {
        Auth::checkAdmin();
        $articles = News::getAll();

        (new ResponseHandler('admin/news.html.twig'))
            ->with('articles', $articles)
            ->render();
    }

    public function showNewsForm(?int $id = null): void
    {
        Auth::checkAdmin();
        $article = null;
        if ($id !== null) {
            $article = News::findById($id);
            if ($article === null) {
                Session::flash('error', 'Article not found.');
                Response::redirect('/admin/news');
            }
        }

        (new ResponseHandler('admin/news-form.html.twig'))
            ->with('article', $article)
            ->render();
    }

    public function saveNews(): void
    {
        Auth::checkAdmin();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token.');
            Response::redirect('/admin/news');
        }

        $validator = Validator::make($_POST)
            ->required('title')
            ->required('content')
            ->max('title', 255);

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/admin/news/new');
            return;
        }

        $data = [
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if (isset($_POST['id']) && $_POST['id'] !== '') {
            News::update((int) $_POST['id'], $data);
        } else {
            News::create($data);
        }

        Session::flash('success', 'Article saved successfully.');
        Response::redirect('/admin/news');
    }

    public function toggleNews(int $id): void
    {
        Auth::checkAdmin();
        News::toggleActive($id);
        Session::flash('success', 'Article updated.');
        Response::redirect('/admin/news');
    }

    public function deleteNews(int $id): void
    {
        Auth::checkAdmin();
        News::delete($id);
        Session::flash('success', 'Article deleted.');
        Response::redirect('/admin/news');
    }

    public function stats(): void
    {
        Auth::checkAdmin();
        (new ResponseHandler('admin/stats.html.twig'))->render();
    }
}

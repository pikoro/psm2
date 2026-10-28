<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Response;
use App\ResponseHandler;
use App\Session;
use App\CSRF;
use App\Validator;
use App\Models\User;
use App\Models\Item;
use App\Models\Feedback;

final class ProfileController
{
    public function show(int $id): void
    {
        $user = User::findById($id);
        if ($user === null || !$user['is_active']) {
            Session::flash('error', 'User not found.');
            Response::redirect('/');
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;
        $itemsResult = Item::getOwnerItems($id, $page, $perPage);

        $feedbacks = Feedback::getByUserId($id);

        (new ResponseHandler('profile/show.html.twig'))
            ->with('user', $user)
            ->with('items', $itemsResult['items'])
            ->with('pagination', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $itemsResult['total'],
                'base_url' => '/profile/' . $id,
            ])
            ->with('feedbacks', $feedbacks)
            ->render();
    }

    public function showEdit(): void
    {
        Auth::check();
        $user = User::findById(Auth::getId());

        (new ResponseHandler('profile/edit.html.twig'))
            ->with('user', $user)
            ->render();
    }

    public function updateProfile(): void
    {
        Auth::check();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/profile/edit');
        }

        $validator = Validator::make($_POST)
            ->required('username')
            ->required('email')
            ->email('email')
            ->min('username', 3)
            ->max('username', 50)
            ->unique('username', 'users', 'username', Auth::getId());

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/profile/edit');
            return;
        }

        $emailValidator = Validator::make($_POST)
            ->unique('email', 'users', 'email', Auth::getId());

        if (!$emailValidator->validate()) {
            Session::flash('error', 'That email is already in use.');
            Session::flash('validation_errors', $emailValidator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/profile/edit');
            return;
        }

        $updateData = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'paypal_email' => $_POST['paypal_email'] ?? null,
            'first_name' => $_POST['first_name'] ?? '',
            'last_name' => $_POST['last_name'] ?? '',
        ];

        if (!empty($_POST['bio'])) {
            $updateData['bio'] = $_POST['bio'];
        }

        // Update language preference
        $language = $_POST['language'] ?? 'en';
        if (\App\Translation\TranslationManager::isSupported($language)) {
            $updateData['language'] = $language;
        }

        User::updateProfile(Auth::getId(), $updateData);

        Session::flash('success', 'Profile updated successfully!');
        Response::redirect('/profile/' . Auth::getId());
    }

    public function submitFeedback(int $toUserId): void
    {
        Auth::check();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/profile/' . $toUserId);
        }

        $validator = Validator::make($_POST)
            ->required('rating')
            ->numeric('rating')
            ->between('rating', 1, 5);

        if (!$validator->validate()) {
            Session::flash('error', 'Please provide a valid rating.');
            Response::redirect('/profile/' . $toUserId);
            return;
        }

        // Prevent duplicate feedback
        $existing = Feedback::getByItemAndUsers(Auth::getId(), $toUserId);
        if ($existing !== null) {
            Session::flash('error', 'You have already left feedback for this user on this item.');
            Response::redirect('/profile/' . $toUserId);
            return;
        }

        Feedback::create(Auth::getId(), $toUserId, (int) ($_GET['item'] ?? 0), (int) $_POST['rating'], $_POST['comment'] ?? null);

        Session::flash('success', 'Feedback submitted!');
        Response::redirect('/profile/' . $toUserId);
    }
}

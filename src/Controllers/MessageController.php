<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Response;
use App\ResponseHandler;
use App\Session;
use App\CSRF;
use App\Validator;
use App\Models\Message;

final class MessageController
{
    public function inbox(): void
    {
        Auth::check();
        $userId = Auth::getId();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $result = Message::getInbox($userId, $page, $perPage);
        $unreadCount = Message::getUnreadCount($userId);

        (new ResponseHandler('messages/inbox.html.twig'))
            ->with('messages', $result['messages'])
            ->with('pagination', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'base_url' => '/messages/inbox',
            ])
            ->with('unread_count', $unreadCount)
            ->render();
    }

    public function sent(): void
    {
        Auth::check();
        $userId = Auth::getId();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $result = Message::getSent($userId, $page, $perPage);

        (new ResponseHandler('messages/sent.html.twig'))
            ->with('messages', $result['messages'])
            ->with('pagination', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'base_url' => '/messages/sent',
            ])
            ->render();
    }

    public function trash(): void
    {
        Auth::check();
        $userId = Auth::getId();
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $result = Message::getTrash($userId, $page, $perPage);

        (new ResponseHandler('messages/trash.html.twig'))
            ->with('messages', $result['messages'])
            ->with('pagination', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'base_url' => '/messages/trash',
            ])
            ->render();
    }

    public function showCompose(): void
    {
        Auth::check();
        $toUserId = (int) ($_GET['to'] ?? 0);
        $itemId = (int) ($_GET['item'] ?? 0);
        $replyToId = (int) ($_GET['reply'] ?? 0);

        $defaultSubject = '';
        $defaultBody = '';

        if ($replyToId > 0) {
            $msg = Message::getById($replyToId);
            if ($msg && $msg['to_user_id'] === Auth::getId()) {
                $defaultSubject = 'Re: ' . $msg['subject'];
                $defaultBody = "\n\n--- Original message from " . $msg['sender_name'] . " ---\n" . $msg['body'];
            }
        } elseif ($itemId > 0) {
            $item = \App\Models\Item::findById($itemId);
            if ($item) {
                $defaultSubject = 'Re: ' . $item['name'];
                $defaultBody = "\n\nHi, I'm interested in your listing: " . $item['name'];
            }
        }

        $toUser = null;
        if ($toUserId > 0) {
            $toUser = \App\Models\User::findById($toUserId);
        }

        (new ResponseHandler('messages/compose.html.twig'))
            ->with('to_user', $toUser)
            ->with('item_id', $itemId)
            ->with('default_subject', $defaultSubject)
            ->with('default_body', $defaultBody)
            ->render();
    }

    public function compose(): void
    {
        Auth::check();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/messages/compose');
        }

        $validator = Validator::make($_POST)
            ->required('to_user_id')
            ->required('subject')
            ->required('body')
            ->max('subject', 255)
            ->min('body', 1);

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/messages/compose');
            return;
        }

        $toUserId = (int) $_POST['to_user_id'];
        $toUser = \App\Models\User::findById($toUserId);
        if ($toUser === null || !$toUser['is_active']) {
            Session::flash('error', 'Invalid recipient.');
            Response::redirect('/messages/compose');
        }

        if ($toUserId === Auth::getId()) {
            Session::flash('error', 'You cannot send a message to yourself.');
            Response::redirect('/messages/compose');
        }

        Message::create(Auth::getId(), $toUserId, $_POST['subject'], $_POST['body']);

        Session::flash('success', 'Message sent successfully!');
        Response::redirect('/messages/inbox');
    }

    public function show(int $id): void
    {
        Auth::check();
        $msg = Message::getById($id);
        if ($msg === null) {
            Session::flash('error', 'Message not found.');
            Response::redirect('/messages/inbox');
        }

        // Check permissions
        if ($msg['to_user_id'] !== Auth::getId() && $msg['from_user_id'] !== Auth::getId()) {
            Session::flash('error', 'You do not have access to this message.');
            Response::redirect('/messages/inbox');
        }

        Message::markAsRead($id);

        (new ResponseHandler('messages/show.html.twig'))
            ->with('message', $msg)
            ->render();
    }

    public function markRead(int $id): void
    {
        Auth::check();
        Message::markAsRead($id);
        Session::flash('success', 'Message marked as read.');
        Response::redirect('/messages/inbox');
    }

    public function moveToTrash(int $id, string $role = 'receiver'): void
    {
        Auth::check();
        Message::moveToTrash($id, Auth::getId(), $role);
        Session::flash('success', 'Message moved to trash.');
        Response::redirect('/messages/inbox');
    }

    public function permanentlyDelete(int $id): void
    {
        Auth::check();
        Message::permanentlyDelete($id);
        Session::flash('success', 'Message permanently deleted.');
        Response::redirect('/messages/trash');
    }

    public function emptyTrash(): void
    {
        Auth::check();
        // Get all trashed messages for this user
        $userId = Auth::getId();
        $result = Message::getTrash($userId, 1, 10000);
        foreach ($result['messages'] as $msg) {
            Message::permanentlyDelete($msg['id']);
        }
        Session::flash('success', 'Trash emptied.');
        Response::redirect('/messages/trash');
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Response;
use App\ResponseHandler;
use App\Session;
use App\CSRF;
use App\Validator;
use App\Models\Item;
use App\Models\Category;

final class ItemController
{
    public function browse(): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $filters = [];

        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = (int) $_GET['category_id'];
        }
        if (!empty($_GET['post_type'])) {
            $filters['post_type'] = $_GET['post_type'];
        }

        $result = Item::getAll($page, $perPage, $filters);
        $categories = Category::getAll();

        (new ResponseHandler('items/index.html.twig'))
            ->with('items', $result['items'])
            ->with('pagination', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'base_url' => '/browse?' . http_build_query($filters),
            ])
            ->with('categories', $categories)
            ->with('filters', $filters)
            ->render();
    }

    public function show(int $id): void
    {
        $item = Item::findById($id);
        if ($item === null) {
            Session::flash('error', 'Item not found.');
            Response::redirect('/browse');
        }

        $photos = Item::getPhotos($id);

        (new ResponseHandler('items/show.html.twig'))
            ->with('item', $item)
            ->with('photos', $photos)
            ->render();
    }

    public function showCreate(): void
    {
        Auth::check();
        $categories = Category::getAll();
        (new ResponseHandler('items/create.html.twig'))
            ->with('categories', $categories)
            ->render();
    }

    public function create(): void
    {
        Auth::check();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/items/create');
        }

        $validator = Validator::make($_POST)
            ->required('name')
            ->required('description')
            ->required('category_id')
            ->min('name', 3)
            ->max('name', 200)
            ->in('post_type', 'offer,want');

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            $categories = Category::getAll();
            (new ResponseHandler('items/create.html.twig'))
                ->with('categories', $categories)
                ->render();
            return;
        }

        $ownerId = Auth::getId();
        $itemId = Item::create($_POST, $ownerId);

        // Handle file uploads
        if (isset($_FILES['photos']) && $_FILES['photos']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $order = 0;
            foreach ($_FILES['photos']['name'] as $i => $fileName) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK && $fileName !== '') {
                    $tmpPath = $_FILES['photos']['tmp_name'][$i];
                    $uploadPath = $this->saveUpload($tmpPath, $fileName);
                    if ($uploadPath !== null) {
                        Item::addPhoto($itemId, $uploadPath, $order++);
                    }
                }
            }
        }

        Session::flash('success', 'Item listed successfully!');
        Response::redirect('/items/' . $itemId);
    }

    public function showEdit(int $id): void
    {
        Auth::check();
        $item = Item::findById($id);
        if ($item === null || $item['owner_id'] !== Auth::getId()) {
            Session::flash('error', 'Item not found or you do not own it.');
            Response::redirect('/browse');
        }

        $categories = Category::getAll();
        (new ResponseHandler('items/edit.html.twig'))
            ->with('item', $item)
            ->with('categories', $categories)
            ->render();
    }

    public function update(int $id): void
    {
        Auth::check();

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/items/' . $id . '/edit');
        }

        $item = Item::findById($id);
        if ($item === null || $item['owner_id'] !== Auth::getId()) {
            Session::flash('error', 'Item not found or you do not own it.');
            Response::redirect('/browse');
        }

        $validator = Validator::make($_POST)
            ->required('name')
            ->required('description')
            ->required('category_id')
            ->min('name', 3)
            ->max('name', 200)
            ->in('post_type', 'offer,want');

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            $categories = Category::getAll();
            (new ResponseHandler('items/edit.html.twig'))
                ->with('item', $item)
                ->with('categories', $categories)
                ->render();
            return;
        }

        Item::update($id, $_POST);

        // Handle file uploads
        if (isset($_FILES['photos']) && $_FILES['photos']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $order = count(Item::getPhotos($id));
            foreach ($_FILES['photos']['name'] as $i => $fileName) {
                if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_OK && $fileName !== '') {
                    $tmpPath = $_FILES['photos']['tmp_name'][$i];
                    $uploadPath = $this->saveUpload($tmpPath, $fileName);
                    if ($uploadPath !== null) {
                        Item::addPhoto($id, $uploadPath, $order++);
                    }
                }
            }
        }

        Session::flash('success', 'Item updated successfully!');
        Response::redirect('/items/' . $id);
    }

    public function deactivate(int $id): void
    {
        Auth::check();
        $item = Item::findById($id);
        if ($item === null || $item['owner_id'] !== Auth::getId()) {
            Session::flash('error', 'Item not found or you do not own it.');
            Response::redirect('/browse');
        }

        Item::deactivate($id);
        Session::flash('success', 'Item deactivated.');
        Response::redirect('/desktop');
    }

    public function activate(int $id): void
    {
        Auth::check();
        $item = Item::findById($id);
        if ($item === null || $item['owner_id'] !== Auth::getId()) {
            Session::flash('error', 'Item not found or you do not own it.');
            Response::redirect('/browse');
        }

        Item::activate($id);
        Session::flash('success', 'Item reactivated.');
        Response::redirect('/desktop');
    }

    private function saveUpload(string $tmpPath, string $fileName): ?string
    {
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            return null;
        }

        // Validate it's actually an image
        $imageInfo = getimagesize($tmpPath);
        if ($imageInfo === false) {
            return null;
        }

        // Validate file size (5MB max)
        if (filesize($tmpPath) > 5 * 1024 * 1024) {
            return null;
        }

        // Generate safe filename
        $ext = $allowedTypes[$mimeType];
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../../public/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . $safeName;
        if (move_uploaded_file($tmpPath, $destPath)) {
            chmod($destPath, 0644);
            return $safeName;
        }

        return null;
    }
}

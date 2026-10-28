<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use App\ResponseHandler;
use App\Session;
use App\Models\Item;

final class SearchController
{
    public function index(): void
    {
        $keyword = trim($_GET['q'] ?? '');
        if ($keyword === '') {
            Response::redirect('/browse');
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;

        $result = Item::search($keyword, $page, $perPage);

        (new ResponseHandler('search/index.html.twig'))
            ->with('keyword', $keyword)
            ->with('items', $result['items'])
            ->with('pagination', [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'base_url' => '/search?q=' . urlencode($keyword),
            ])
            ->render();
    }
}

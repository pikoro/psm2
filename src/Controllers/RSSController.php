<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use App\Models\Item;
use App\Models\News;

final class RSSController
{
    public function items(): void
    {
        $items = Item::getRecentItems(50);

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $this->generateRSSFeed('PHPSwapMeet 2 - Items', 'Items listed on PHPSwapMeet 2', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), $items);
    }

    public function news(): void
    {
        $articles = News::getActiveArticles();

        header('Content-Type: application/rss+xml; charset=utf-8');
        echo $this->generateRSSFeed('PHPSwapMeet 2 - News', 'Latest news from PHPSwapMeet 2', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), $articles, true);
    }

    private function generateRSSFeed(string $title, string $description, string $baseUrl, array $items, bool $isNews = false): string
    {
        $escape = function (string $str): string {
            return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= '  <channel>' . "\n";
        $xml .= '    <title>' . $escape($title) . '</title>' . "\n";
        $xml .= '    <link>' . $escape($baseUrl) . '</link>' . "\n";
        $xml .= '    <description>' . $escape($description) . '</description>' . "\n";
        $xml .= '    <atom:link href="' . $escape($baseUrl . '/rss/items') . '" rel="self" type="application/rss+xml" />' . "\n";

        foreach ($items as $item) {
            $link = $isNews
                ? $baseUrl . '/admin/news#' . $item['id']
                : $baseUrl . '/items/' . $item['id'];

            $xml .= '    <item>' . "\n";
            $xml .= '      <title>' . $escape($item['title'] ?? $item['name']) . '</title>' . "\n";
            $xml .= '      <link>' . $escape($link) . '</link>' . "\n";
            $xml .= '      <guid>' . $escape($link) . '</guid>' . "\n";

            if (isset($item['content']) || isset($item['description'])) {
                $xml .= '      <description>' . $escape($item['content'] ?? $item['description']) . '</description>' . "\n";
            }

            if (isset($item['created_at'])) {
                $xml .= '      <pubDate>' . gmdate('D, d M Y H:i:s +0000', strtotime($item['created_at'])) . '</pubDate>' . "\n";
            }

            if (isset($item['owner_username']) || isset($item['author'])) {
                $xml .= '      <author>' . $escape($item['owner_username'] ?? $item['author']) . '</author>' . "\n";
            }

            $xml .= '    </item>' . "\n";
        }

        $xml .= '  </channel>' . "\n";
        $xml .= '</rss>';

        return $xml;
    }
}

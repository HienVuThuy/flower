<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml: danh sách trang công khai cho công cụ tìm kiếm. */
class SitemapController extends Controller
{
    private const NHO_PHUT = 60;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addMinutes(self::NHO_PHUT), fn () => $this->dung());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function dung(): string
    {
        $dong = [];

        foreach (['welcome', 'shop.products.index', 'shop.categories.index', 'shop.blog.index', 'shop.community.index'] as $ten) {
            $dong[] = [route($ten), null, '0.8'];
        }

        Product::query()
            ->mainCatalog()
            ->whereIn('status', ['active', 'out_of_stock'])
            ->select(['id', 'slug', 'updated_at'])
            ->chunk(200, function ($ds) use (&$dong) {
                foreach ($ds as $sp) {
                    $dong[] = [route('shop.products.show', $sp), $sp->updated_at, '0.9'];
                }
            });

        foreach (Category::query()->where('is_active', true)->get(['id', 'slug', 'updated_at']) as $dm) {
            $dong[] = [route('shop.categories.show', $dm), $dm->updated_at, '0.7'];
        }

        foreach (BlogPost::query()->published()->get(['id', 'slug', 'updated_at']) as $bai) {
            $dong[] = [route('shop.blog.show', $bai), $bai->updated_at, '0.6'];
        }

        foreach (array_keys(PageController::PAGES) as $slug) {
            $dong[] = [route('shop.pages.show', $slug), null, '0.4'];
        }

        $xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

        foreach ($dong as [$url, $sua, $uuTien]) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . e($url) . '</loc>';

            if ($sua !== null) {
                $xml[] = '    <lastmod>' . $sua->toAtomString() . '</lastmod>';
            }

            $xml[] = '    <priority>' . $uuTien . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml);
    }
}

<?php

namespace App\Services\Search;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dựng nội dung hai cột products.search_name / products.search_text.
 * ============================================================
 * MỘT NƠI DUY NHẤT quyết định "sản phẩm này tìm bằng những chữ nào".
 * Sửa công thức ở đây rồi chạy `php artisan search:reindex` là toàn bộ
 * catalog cập nhật theo — không có bản sao công thức nào ở chỗ khác.
 *
 * Được gọi tự động ở hai nơi (xem App\Observers\ProductObserver và
 * CategoryObserver), nên admin sửa sản phẩm hay đổi tên danh mục là chỉ
 * mục tự đúng lại, không cần ai nhớ chạy lệnh.
 */
class ProductSearchIndexer
{
    /**
     * Khoá đếm phiên bản từ điển.
     *
     * Từ điển gợi ý sửa lỗi gõ được cache; mỗi lần chỉ mục đổi thì tăng
     * số này lên, khoá cache đổi theo và bản cũ tự bị bỏ qua. Cách này
     * an toàn hơn Cache::forget() vì không phụ thuộc việc xoá có thành
     * công hay không — bản cũ còn nằm đó cũng không ai đọc nữa.
     */
    public const VERSION_KEY = 'search.dictionary.version';

    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {
    }

    /**
     * Hai giá trị chỉ mục cho một sản phẩm.
     *
     * @return array{search_name: string, search_text: string}
     */
    public function values(Product $product): array
    {
        $name = $this->normalizer->normalize($product->name);

        /*
         * search_text gom NHỮNG GÌ KHÁCH THẬT SỰ GÕ, không phải mọi chữ
         * có trên trang.
         *
         * CÓ tên danh mục và nhãn hình thức bán: "cây để bàn", "bó hoa"
         * là cách gọi tự nhiên của khách, nhưng không nằm trong tên sản
         * phẩm nào cả.
         *
         * KHÔNG có `description` (mô tả dài): nó chứa hàng trăm chữ về
         * cách chăm, cách gói, chính sách đổi trả. Nhét vào đây thì gõ
         * "đổi trả" là ra sạch catalog — nhiễu nhiều hơn tin.
         */
        $parts = [
            $product->name,
            $product->product_code,
            $product->short_description,
            $product->category?->name,
            $product->selling_form?->label(),
            $product->product_type?->label(),
        ];

        $text = $this->normalizer->normalize(implode(' ', array_filter($parts)));

        return [
            'search_name' => mb_substr($name, 0, 255),
            // Cắt theo đúng độ rộng cột. Cắt cụt một từ ở cuối chỉ làm
            // mất một từ khoá phụ, còn để tràn cột là lỗi ghi dữ liệu.
            'search_text' => mb_substr($this->dedupe($text), 0, 1000),
        ];
    }

    /**
     * Gán giá trị chỉ mục vào model mà KHÔNG lưu.
     *
     * Dùng trong sự kiện `saving` để đi chung một câu UPDATE với các thay
     * đổi khác — gán rồi save() lần nữa sẽ thành hai lượt ghi và kích
     * hoạt lại chính sự kiện này.
     */
    public function fill(Product $product): void
    {
        // Tên danh mục nằm ở bảng khác. Nạp sẵn để không phải truy vấn
        // lại nếu quan hệ chưa được load.
        if ($product->category_id && ! $product->relationLoaded('category')) {
            $product->load('category');
        }

        foreach ($this->values($product) as $column => $value) {
            $product->setAttribute($column, $value);
        }
    }

    /**
     * Dựng lại chỉ mục cho toàn bộ catalog.
     *
     * @return array{scanned: int, written: int}
     *
     * Ghi thẳng bằng query builder chứ không dùng $product->save():
     * save() sẽ chạm timestamps và bắn lại sự kiện model, biến một lệnh
     * bảo trì thành ra "mọi sản phẩm vừa được sửa" — sai sự thật, và ảnh
     * hưởng tới cả sắp xếp "Mới nhất" trên trang danh sách.
     */
    public function reindexAll(): array
    {
        $written = 0;
        $scanned = 0;

        Product::query()
            ->withTrashed()
            ->with('category')
            ->chunkById(200, function ($products) use (&$written, &$scanned) {
                foreach ($products as $product) {
                    $scanned++;
                    $values = $this->values($product);

                    // Bỏ qua bản ghi đã đúng: tránh ghi thừa lên đĩa và
                    // giữ cho số trả về nói đúng "có bao nhiêu thứ đổi".
                    if ($product->search_name === $values['search_name']
                        && $product->search_text === $values['search_text']) {
                        continue;
                    }

                    DB::table('products')->where('id', $product->id)->update($values);
                    $written++;
                }
            });

        $this->bumpVersion();

        return ['scanned' => $scanned, 'written' => $written];
    }

    /** Dựng lại chỉ mục cho các sản phẩm thuộc một danh mục. */
    public function reindexCategory(Category $category): int
    {
        $written = 0;

        Product::query()
            ->withTrashed()
            ->where('category_id', $category->id)
            ->chunkById(200, function ($products) use (&$written, $category) {
                foreach ($products as $product) {
                    $product->setRelation('category', $category);
                    DB::table('products')->where('id', $product->id)->update($this->values($product));
                    $written++;
                }
            });

        $this->bumpVersion();

        return $written;
    }

    /** Báo cho từ điển biết dữ liệu đã đổi. */
    public function bumpVersion(): void
    {
        // Cache::increment không tạo khoá nếu chưa có, nên phải đặt nền.
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * Bỏ từ trùng, giữ nguyên thứ tự xuất hiện.
     *
     * Tên sản phẩm và tên danh mục hay lặp nhau ("Hoa hồng đỏ Ecuador"
     * thuộc danh mục "Hoa"), nên bước này thu ngắn cột đáng kể mà không
     * mất từ khoá nào.
     */
    private function dedupe(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return implode(' ', array_keys(array_flip(explode(' ', $text))));
    }
}

<?php

namespace App\Models;

use App\Enums\TaxonRank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Một nút trong cây phân loại thực vật.
 * ============================================================
 * Xem migration `create_plant_taxa_table` để biết vì sao đây là một cây
 * cha–con chứ không phải bảy cái nhãn phẳng.
 */
class PlantTaxon extends Model
{
    protected $table = 'plant_taxa';

    protected $fillable = [
        'parent_id',
        'rank',
        'name',
        'scientific_name',
        'slug',
        'description',
        'image',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rank' => TaxonRank::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** Sản phẩm gắn TRỰC TIẾP vào nút này. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'taxon_id');
    }

    /**
     * Đường dẫn phân loại từ Giới xuống tới nút này.
     *
     * ĐI NGƯỢC LÊN THEO `parent`, không đệ quy xuống. Sâu tối đa bảy
     * bậc nên số truy vấn có trần cứng — không có nguy cơ N+1 mất kiểm
     * soát như duyệt xuống.
     *
     * Trả về theo thứ tự đọc được: Giới trước, nút này sau cùng.
     *
     * @return Collection<int, self>
     */
    public function chain(): Collection
    {
        $chuoi = collect([$this]);
        $nut = $this;

        /*
         * Chặn vòng lặp vô hạn bằng số bậc tối đa.
         *
         * Dữ liệu đúng thì không bao giờ có vòng, nhưng `parent_id` là
         * một cột bình thường và một lần sửa tay có thể tạo ra A→B→A.
         * Khi ấy trang sản phẩm sẽ treo cho tới khi hết bộ nhớ, và
         * nguyên nhân thì nằm ở một hàng trong bảng khác.
         */
        $conLai = count(TaxonRank::cases());

        while ($nut->parent_id && $conLai-- > 0) {
            $nut = $nut->parent;

            if (! $nut) {
                break;
            }

            $chuoi->prepend($nut);
        }

        return $chuoi;
    }

    /**
     * Id của nút này và MỌI nút con cháu.
     *
     * Dùng để trả lời "cho tôi xem mọi cây thuộc họ Ráy" — sản phẩm có
     * thể gắn ở bất kỳ bậc nào bên dưới.
     *
     * LẤY CẢ BẢNG RỒI DỰNG CÂY TRONG PHP, không truy vấn đệ quy.
     * Cây phân loại của một cửa hàng cây cảnh có cỡ vài trăm nút — một
     * câu `select id, parent_id` là đủ và luôn rẻ hơn bảy lượt đi vòng.
     * Cách này cũng chạy trên mọi bản MySQL/MariaDB, kể cả bản không có
     * CTE đệ quy.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $canh = self::query()->get(['id', 'parent_id'])->groupBy('parent_id');

        $ket = [$this->id];
        $hangDoi = [$this->id];

        while ($hangDoi) {
            $id = array_shift($hangDoi);

            foreach ($canh->get($id, collect()) as $con) {
                $ket[] = $con->id;
                $hangDoi[] = $con->id;
            }
        }

        return $ket;
    }

    /**
     * Tên kèm bậc: "Họ Ráy", "Chi Monstera".
     *
     * BẬC KHÔNG NẰM TRONG CỘT `name`, và đó là chủ ý.
     *
     * LỖI ĐÃ SỬA: bản đầu lưu thẳng "Họ Ráy" vào cột name. Ở chỗ tên
     * đứng một mình (ô danh sách) thì đọc đúng, nhưng ở chỗ có cột bậc
     * riêng bên cạnh — đường dẫn phân loại ở trang sản phẩm — nó thành
     * "Họ | Họ Ráy". Bảy dòng, bảy lần lặp, ngay giữa khối thông tin
     * đáng lẽ để tra cứu.
     *
     * Lưu bậc một chỗ và ghép khi cần thì chỗ nào cũng đọc đúng, và
     * đổi cách gọi về sau chỉ phải sửa ở đây.
     */
    public function displayName(): string
    {
        return $this->rank->label() . ' ' . $this->name;
    }

    /** Tên hiển thị đầy đủ: "Họ Ráy (Araceae)". */
    public function fullName(): string
    {
        return $this->scientific_name
            ? sprintf('%s (%s)', $this->displayName(), $this->scientific_name)
            : $this->displayName();
    }

    /** Chỉ những nút ở một bậc nhất định. */
    public function scopeRank(Builder $query, TaxonRank $rank): Builder
    {
        return $query->where('rank', $rank);
    }

    /** Nút gốc — không có cha. */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}

<?php

namespace App\Services\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Lớp nối thẳng tới API của Giao Hàng Nhanh.
 * ============================================================
 * NƠI DUY NHẤT gọi ra ngoài tới GHN. Nhiệm vụ đúng như tài liệu hướng
 * dẫn nêu: gửi Token/ShopId, lấy tỉnh–quận–phường, tính phí, tạo và huỷ
 * vận đơn, xử lý lỗi kết nối.
 *
 * ĐẶT TRONG App\Services\Shipping THAY VÌ App\Services.
 * Tài liệu để ở `App\Services` trần, nhưng dự án này đã chia dịch vụ
 * theo nghiệp vụ (Auth, Cart, Order, Coupon, Pricing…) và đã có sẵn
 * `App\Services\Shipping\ShippingRates`. Để GHN nằm cùng chỗ với phần
 * tính phí giao là giữ đúng một chỗ cho một nghiệp vụ; tên lớp giữ
 * nguyên như tài liệu.
 *
 * KHÔNG BAO GIỜ NÉM LỖI RA NGOÀI.
 * Mọi hàm trả về mảng, kể cả khi GHN sập hay mất mạng. Lý do: phí giao
 * là một phần của màn hình thanh toán, và một dịch vụ bên ngoài chết
 * không được phép làm khách không đặt được hàng. Nơi gọi tự quyết định
 * làm gì với `code` khác 200 — xem ShippingQuote.
 */
class GHNService
{
    protected string $baseUrl;

    protected string $token;

    protected int $shopId;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.ghn.base_url');
        $this->token = (string) (config('services.ghn.token') ?? '');
        $this->shopId = (int) config('services.ghn.shop_id', 0);
    }

    /**
     * Đang nối cổng THỬ của GHN hay không.
     *
     * Cước trên cổng thử là bảng giá thử. Báo cáo cước mà không nói điều
     * này thì người đọc tưởng đó là tiền cửa hàng thật sự trả.
     */
    public function isSandbox(): bool
    {
        return str_contains($this->baseUrl, 'dev-online-gateway');
    }

    /** Đã khai đủ token và shop id chưa. */
    public function configured(): bool
    {
        return $this->token !== '' && $this->shopId > 0 && $this->baseUrl !== '';
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withOptions([
                /*
                 * verify_ssl=false CHỈ dành cho máy phát triển: XAMPP
                 * trên Windows thường thiếu bộ chứng chỉ gốc nên mọi
                 * kết nối HTTPS đều hỏng. Trên máy chủ thật phải là
                 * true — xem chú thích ở config/services.php.
                 */
                'verify' => filter_var(
                    config('services.ghn.verify_ssl', true),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            ])
            ->acceptJson()
            /*
             * 15 giây là mức tài liệu GHN đề nghị. Phải CÓ một giới hạn:
             * không đặt thì một lần GHN treo là request của khách treo
             * theo cho tới khi PHP tự cắt, và cả tiến trình web bị giữ.
             */
            ->timeout(15)
            ->withHeaders([
                'Token' => $this->token,
                'ShopId' => $this->shopId,
                'Content-Type' => 'application/json',
            ]);
    }

    /* ============ DANH MỤC ĐỊA GIỚI ============ */

    /**
     * Danh sách tỉnh/thành.
     *
     * ĐỆM LẠI 24 GIỜ. Danh mục địa giới gần như không đổi, mà mỗi lần mở
     * trang thanh toán là một lần gọi ra ngoài — chậm cho khách và tốn
     * hạn mức của cửa hàng. Đệm ở đây chứ không ở controller: mọi nơi
     * dùng đều được lợi, và chỉ có một chỗ quyết định thời gian đệm.
     */
    public function getProvinces(): array
    {
        $res = $this->cached('ghn.provinces', fn () => $this->get('/master-data/province'));

        if (($res['code'] ?? null) !== 200 || ! is_array($res['data'] ?? null)) {
            return $res;
        }

        /*
         * LOẠI BỎ BẢN GHI RÁC CỦA MÔI TRƯỜNG THỬ.
         *
         * Cổng thử của GHN lẫn bản ghi rác vào danh mục thật, và KHÔNG
         * có cờ nào phân biệt — cả ba đều `Status = 1`:
         *
         *     2002  "Hà Nội 02"                  → 0 quận/huyện
         *     298   "Test - Alert - Tỉnh - 001"  → 0 quận/huyện
         *     201   "Hà Nội"                     → 30 quận/huyện
         *
         * Hai mục tên gần giống nhau nằm cạnh nhau trong danh sách thả
         * xuống; chọn nhầm là chuyện gần như chắc chắn xảy ra, và khi đó
         * ô quận/huyện rỗng mà không có gì giải thích.
         *
         * VÌ SAO LÀ DANH SÁCH CỐ ĐỊNH CHỨ KHÔNG TỰ DÒ:
         *
         * Bản đầu tự dò bằng cách gọi `/master-data/district` không kèm
         * tham số để lấy toàn bộ 727 quận/huyện rồi suy ra tập tỉnh hợp
         * lệ. Nghe hợp lý, nhưng ĐO RA THÌ KHÔNG DÙNG ĐƯỢC: lời gọi đó
         * trả về nhỏ giọt và không bao giờ xong — 40 giây vẫn còn đang
         * nhận dữ liệu rồi hết giờ. Nó nằm ngay trên đường mở trang
         * thanh toán, nên mỗi khách phải chờ hết ngần ấy giây cho một
         * việc chỉ để giấu hai dòng rác.
         *
         * Danh sách cố định thì thô hơn, nhưng đúng và tốn 0 giây. Đây
         * là dữ liệu của MÔI TRƯỜNG THỬ — cổng thật không có mấy bản ghi
         * này, nên để rỗng khi chạy thật.
         *
         * Và dù có bỏ sót bản rác nào, khách cũng không mắc kẹt: chọn
         * phải tỉnh rỗng thì giao diện nói thẳng ra — xem ghn-address.js.
         */
        $bo = (array) config('services.ghn.skip_province_ids', []);

        if ($bo === []) {
            return $res;
        }

        $res['data'] = array_values(array_filter(
            $res['data'],
            fn ($p) => ! in_array((int) ($p['ProvinceID'] ?? 0), $bo, true),
        ));

        return $res;
    }

    public function getDistricts(int $provinceId): array
    {
        return $this->cached(
            "ghn.districts.{$provinceId}",
            fn () => $this->get('/master-data/district', ['province_id' => $provinceId]),
        );
    }

    public function getWards(int $districtId): array
    {
        return $this->cached(
            "ghn.wards.{$districtId}",
            fn () => $this->get('/master-data/ward', ['district_id' => $districtId]),
        );
    }

    /* ============ PHÍ VÀ VẬN ĐƠN ============ */

    /** Tính phí giao hàng. */
    public function calculateFee(array $params): array
    {
        return $this->post('/v2/shipping-order/fee', array_merge([
            'shop_id' => $this->shopId,
        ], $params));
    }

    /** Tạo vận đơn. */
    public function createOrder(array $orderData): array
    {
        return $this->post('/v2/shipping-order/create', array_merge([
            'shop_id' => $this->shopId,
        ], $orderData));
    }

    /**
     * Tình trạng hiện tại của một vận đơn.
     *
     * GHN LÀ NGUỒN SỰ THẬT DUY NHẤT về chuyện hàng đang ở đâu. Cửa hàng
     * không nhìn thấy shipper, nên mọi con số hiển thị cho khách về việc
     * "đang giao / đã giao" mà không hỏi GHN đều là phỏng đoán.
     *
     * Dùng bởi GhnStatusSync — xem chú thích ở đó về việc vì sao điều
     * này quyết định luôn cả trạng thái THANH TOÁN của đơn COD.
     */
    public function orderDetail(string $orderCode): array
    {
        return $this->post('/v2/shipping-order/detail', [
            'order_code' => $orderCode,
        ]);
    }

    /** Huỷ vận đơn. */
    public function cancelOrder(array $orderCodes): array
    {
        return $this->post('/v2/switch-status/cancel', [
            'order_codes' => $orderCodes,
            'shop_id' => $this->shopId,
        ]);
    }

    /**
     * Thông số kiện hàng dùng chung cho cả tính phí lẫn tạo vận đơn.
     *
     * PHẢI DÙNG CHUNG MỘT NGUỒN. Báo giá bằng một bộ kích thước rồi tạo
     * vận đơn bằng bộ khác thì con số khách đã trả và con số GHN thu của
     * cửa hàng lệch nhau — phần chênh cửa hàng chịu, và không ai phát
     * hiện cho tới lúc đối soát cuối tháng.
     */
    public function packageParameters(int $weight): array
    {
        $box = config('services.ghn.box');

        return [
            // GHN từ chối đơn có khối lượng 0.
            'weight' => max(1, $weight),
            'length' => (int) $box['length'],
            'width' => (int) $box['width'],
            'height' => (int) $box['height'],
            'service_type_id' => (int) config('services.ghn.service_type_id', 2),
        ];
    }

    /* ============ TẦNG HTTP ============ */

    /**
     * Đệm kết quả, nhưng CHỈ khi gọi thành công.
     *
     * Đệm cả câu trả lời lỗi là biến một trục trặc mười giây thành một
     * trục trặc hai bốn tiếng: GHN hồi phục rồi mà trang vẫn báo "không
     * tải được tỉnh/thành" cho tới khi hết hạn đệm.
     */
    protected function cached(string $key, callable $fetch): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        /*
         * ĐANG HỎNG THÌ ĐỪNG GỌI LẠI NGAY — trả lời thất bại luôn.
         *
         * VẤN ĐỀ ĐO ĐƯỢC: khi GHN không nhận kết nối, mỗi lần mở trang
         * thanh toán là một lần chờ hết 15 giây rồi mới hỏng. Trang này
         * gọi HAI đường (danh mục tỉnh + danh mục quận/huyện để lọc bản
         * ghi rác), nên khách ngồi nhìn màn hình trắng 30 giây trước khi
         * thấy được cả biểu mẫu — cho một dịch vụ chỉ phụ trách phần
         * phí giao.
         *
         * Nhớ "vừa hỏng" trong 60 giây rồi thôi. Đủ ngắn để GHN sống
         * lại là dùng được ngay, đủ dài để một trục trặc không biến
         * thành hàng trăm lần chờ 15 giây.
         *
         * KHÔNG đệm chính CÂU TRẢ LỜI LỖI 24 giờ như câu trả lời đúng:
         * làm vậy là biến một trục trặc mười giây thành một trục trặc
         * một ngày — GHN hồi phục rồi mà trang vẫn báo "không tải được".
         */
        $khoaHong = $key.'.hong';

        if (Cache::has($khoaHong)) {
            return ['code' => -1, 'message' => 'GHN vừa không phản hồi, đang tạm nghỉ gọi lại.'];
        }

        $result = $fetch();

        if (($result['code'] ?? null) === 200) {
            Cache::put($key, $result, now()->addDay());

            return $result;
        }

        Cache::put($khoaHong, true, now()->addSeconds(60));

        return $result;
    }

    protected function get(string $uri, array $query = []): array
    {
        if (! $this->configured()) {
            return $this->chuaCauHinh();
        }

        try {
            $response = $this->client()->get($uri, $query);

            if (! $response->successful()) {
                Log::warning('GHN GET request failed', [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return ['code' => $response->status(), 'message' => 'GHN API request failed.'];
            }

            return $response->json() ?? ['code' => -1, 'message' => 'GHN returned an empty response.'];
        } catch (ConnectionException $exception) {
            Log::error('Unable to connect to GHN', ['uri' => $uri, 'error' => $exception->getMessage()]);

            return ['code' => -1, 'message' => 'Unable to connect to GHN.'];
        }
    }

    protected function post(string $uri, array $payload): array
    {
        if (! $this->configured()) {
            return $this->chuaCauHinh();
        }

        try {
            $response = $this->client()->post($uri, $payload);

            if (! $response->successful()) {
                /*
                 * GHN trả lời rất rõ vì sao từ chối ("địa chỉ không hỗ
                 * trợ", "khối lượng vượt mức"...). Ghi cả phần thân câu
                 * trả lời vào log, nếu không thì lúc đi tìm nguyên nhân
                 * chỉ có mỗi con số 400.
                 */
                Log::warning('GHN POST request failed', [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return ['code' => $response->status(), 'message' => 'GHN API request failed.'];
            }

            return $response->json() ?? ['code' => -1, 'message' => 'GHN returned an empty response.'];
        } catch (ConnectionException $exception) {
            Log::error('Unable to connect to GHN', ['uri' => $uri, 'error' => $exception->getMessage()]);

            return ['code' => -1, 'message' => 'Unable to connect to GHN.'];
        }
    }

    /**
     * Chưa khai token/shop id thì nói thẳng, đừng gọi ra ngoài.
     *
     * Gọi mà thiếu token chỉ nhận về 401 kèm một dòng log khó hiểu. Trả
     * lời ngay tại chỗ cho người dựng hệ thống biết phải sửa ở đâu.
     */
    private function chuaCauHinh(): array
    {
        Log::warning('GHN chưa được cấu hình: thiếu GHN_TOKEN hoặc GHN_SHOP_ID trong .env');

        return ['code' => -1, 'message' => 'Chưa cấu hình GHN.'];
    }
}

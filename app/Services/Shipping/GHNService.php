<?php

namespace App\Services\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Lớp nối thẳng tới API của Giao Hàng Nhanh. */
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

    public function isSandbox(): bool
    {
        return str_contains($this->baseUrl, 'dev-online-gateway');
    }

    public function configured(): bool
    {
        return $this->token !== '' && $this->shopId > 0 && $this->baseUrl !== '';
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withOptions([
                'verify' => filter_var(
                    config('services.ghn.verify_ssl', true),
                    FILTER_VALIDATE_BOOLEAN,
                ),
            ])
            ->acceptJson()
            ->timeout(15)
            ->withHeaders([
                'Token' => $this->token,
                'ShopId' => $this->shopId,
                'Content-Type' => 'application/json',
            ]);
    }

    public function getProvinces(): array
    {
        $res = $this->cached('ghn.provinces', fn () => $this->get('/master-data/province'));

        if (($res['code'] ?? null) !== 200 || ! is_array($res['data'] ?? null)) {
            return $res;
        }

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

    public function calculateFee(array $params): array
    {
        return $this->post('/v2/shipping-order/fee', array_merge([
            'shop_id' => $this->shopId,
        ], $params));
    }

    public function createOrder(array $orderData): array
    {
        return $this->post('/v2/shipping-order/create', array_merge([
            'shop_id' => $this->shopId,
        ], $orderData));
    }

    public function orderDetail(string $orderCode): array
    {
        return $this->post('/v2/shipping-order/detail', [
            'order_code' => $orderCode,
        ]);
    }

    public function cancelOrder(array $orderCodes): array
    {
        return $this->post('/v2/switch-status/cancel', [
            'order_codes' => $orderCodes,
            'shop_id' => $this->shopId,
        ]);
    }

    public function packageParameters(int $weight): array
    {
        $box = config('services.ghn.box');

        return [
            'weight' => max(1, $weight),
            'length' => (int) $box['length'],
            'width' => (int) $box['width'],
            'height' => (int) $box['height'],
            'service_type_id' => (int) config('services.ghn.service_type_id', 2),
        ];
    }

    protected function cached(string $key, callable $fetch): array
    {
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

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

    private function chuaCauHinh(): array
    {
        Log::warning('GHN chưa được cấu hình: thiếu GHN_TOKEN hoặc GHN_SHOP_ID trong .env');

        return ['code' => -1, 'message' => 'Chưa cấu hình GHN.'];
    }
}

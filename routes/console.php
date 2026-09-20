<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('auth:clear-resets')
    ->daily()
    ->description('Xoá token đặt lại mật khẩu đã hết hạn');


Schedule::command('care:remind')
    ->dailyAt('08:00')
    ->description('Nhắc khách tưới nước / bón phân cho cây đã mua');


Schedule::command('sao-luu:csdl')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->description('Sao lưu cơ sở dữ liệu, giữ 14 bản gần nhất');


Schedule::command('ghn:dong-bo')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->description('Đồng bộ trạng thái vận đơn GHN, tự ghi nhận COD đã thu');


Schedule::command('khuyen-mai:cap-nhat-trang-thai')
    ->hourly()
    ->description('Bật/kết thúc khuyến mại theo ngày đã đặt');


Schedule::command('tra-gop:qua-han')
    ->dailyAt('00:30')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping()
    ->description('Huỷ kế hoạch trả góp có kỳ quá hạn vượt ân hạn');

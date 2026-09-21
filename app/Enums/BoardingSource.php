<?php

namespace App\Enums;

/** Phiếu chăm hộ lập ở đâu: khách tự gửi trên web, hay cửa hàng lập tại quầy. */
enum BoardingSource: string
{
    case Online = 'online';
    case TaiQuay = 'tai_quay';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Khách gửi trên web',
            self::TaiQuay => 'Lập tại quầy',
        };
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/** Xuất toàn bộ cơ sở dữ liệu ra tệp .sql và giữ lại N bản gần nhất. */
class SaoLuuCoSoDuLieu extends Command
{
    protected $signature = 'sao-luu:csdl {--giu=14 : Số bản sao lưu giữ lại}';

    protected $description = 'Sao lưu cơ sở dữ liệu ra storage/app/sao-luu và xoá bản quá cũ';

    public function handle(): int
    {
        $ketNoi = config('database.default');

        if ($ketNoi !== 'mysql' && $ketNoi !== 'mariadb') {
            $this->warn("Chỉ hỗ trợ MySQL/MariaDB, kết nối hiện tại là \"{$ketNoi}\" — bỏ qua.");

            return self::SUCCESS;
        }

        $cauHinh = config("database.connections.{$ketNoi}");
        $thuMuc = storage_path('app/sao-luu');
        File::ensureDirectoryExists($thuMuc);

        $tep = $thuMuc . DIRECTORY_SEPARATOR . $cauHinh['database'] . '-' . now()->format('Ymd-His') . '.sql';
        $lenh = (string) (config('database.mysqldump') ?: env('MYSQLDUMP_PATH', 'mysqldump'));

        $tienTrinh = new Process([
            $lenh,
            '--host=' . $cauHinh['host'],
            '--port=' . $cauHinh['port'],
            '--user=' . $cauHinh['username'],
            '--password=' . $cauHinh['password'],
            '--single-transaction',
            '--routines',
            '--default-character-set=utf8mb4',
            '--result-file=' . $tep,
            $cauHinh['database'],
        ], timeout: 600);

        $tienTrinh->run();

        if (! $tienTrinh->isSuccessful()) {
            @unlink($tep);

            $this->error('Sao lưu thất bại: ' . trim($tienTrinh->getErrorOutput() ?: $tienTrinh->getOutput()));
            $this->line('Nếu máy không tìm thấy mysqldump, đặt đường dẫn đầy đủ vào MYSQLDUMP_PATH trong .env');
            $this->line('Ví dụ trên XAMPP: MYSQLDUMP_PATH="D:\\xampp\\mysql\\bin\\mysqldump.exe"');

            return self::FAILURE;
        }

        $this->info(sprintf('Đã sao lưu: %s (%s KB)', basename($tep), number_format(filesize($tep) / 1024, 0, ',', '.')));
        $this->donDep($thuMuc, (int) $this->option('giu'));

        return self::SUCCESS;
    }

    /** Giữ N bản mới nhất, xoá phần còn lại — sao lưu không dọn thì ổ cứng đầy lúc nào không biết. */
    private function donDep(string $thuMuc, int $giu): void
    {
        $cu = collect(File::files($thuMuc))
            ->filter(fn ($f) => $f->getExtension() === 'sql')
            ->sortByDesc(fn ($f) => $f->getMTime())
            ->slice(max(1, $giu));

        foreach ($cu as $f) {
            File::delete($f->getPathname());
        }

        if ($cu->isNotEmpty()) {
            $this->line('Đã xoá ' . $cu->count() . ' bản sao lưu cũ.');
        }
    }
}

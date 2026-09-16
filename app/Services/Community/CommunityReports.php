<?php

namespace App\Services\Community;

use App\Enums\CommunityReportReason;
use App\Enums\CommunityReportStatus;
use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityReport;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Báo cáo bài / bình luận Góc cây và việc xử lý của cửa hàng. */
class CommunityReports
{
    public function __construct(
        private readonly ActivityLogger $audit,
    ) {
    }

    public function baoCao(User $user, string $loai, int $id, CommunityReportReason $lyDo, ?string $ghiChu): CommunityReport
    {
        $noiDung = $this->noiDungDangHien($loai, $id);

        if (! $noiDung) {
            throw new CommunityException('Nội dung này không còn hiển thị.');
        }

        if ((int) $noiDung->user_id === (int) $user->id) {
            throw new CommunityException('Bạn không thể báo cáo nội dung của chính mình.');
        }

        $daBao = 'Bạn đã báo cáo nội dung này. Cửa hàng đang xem xét.';

        if (CommunityReport::where('reporter_id', $user->id)->where('target_type', $loai)->where('target_id', $id)->exists()) {
            throw new CommunityException($daBao);
        }

        try {
            $bao = new CommunityReport();
            $bao->forceFill([
                'reporter_id' => $user->id,
                'target_type' => $loai,
                'target_id' => $id,
                'reason' => $lyDo,
                'note' => ($ghiChu = trim((string) $ghiChu)) === '' ? null : $ghiChu,
                'status' => CommunityReportStatus::ChoXuLy,
            ])->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                throw new CommunityException($daBao);
            }

            throw $e;
        }

        return $bao;
    }

    public function soNoiDungCho(): int
    {
        return CommunityReport::pending()->distinct()->count(DB::raw("target_type || ':' || target_id"));
    }

    public function hangCho(int $gioiHan = 50): Collection
    {
        $baoCao = CommunityReport::pending()->with('reporter:id,name')->orderByDesc('created_at')->get();

        return $baoCao
            ->groupBy(fn (CommunityReport $b) => $b->target_type . ':' . $b->target_id)
            ->take($gioiHan)
            ->map(function (Collection $nhom) {
                $dau = $nhom->first();
                $noiDung = $dau->target_type === CommunityReport::BAI
                    ? CommunityPost::with(['user:id,name,email', 'media'])->find($dau->target_id)
                    : CommunityComment::with(['user:id,name,email', 'post:id,body'])->find($dau->target_id);

                return [
                    'loai' => $dau->target_type,
                    'id' => (int) $dau->target_id,
                    'so' => $nhom->count(),
                    'ly_do' => $nhom->countBy(fn (CommunityReport $b) => $b->reason->label())->all(),
                    'ghi_chu' => $nhom->pluck('note')->filter()->values()->all(),
                    'moi_nhat' => (string) $dau->created_at,
                    'noi_dung' => $noiDung,
                ];
            })
            ->values();
    }

    public function anNoiDung(User $nguoiXuLy, string $loai, int $id, string $lyDo): void
    {
        DB::transaction(function () use ($nguoiXuLy, $loai, $id, $lyDo) {
            $noiDung = $this->timNoiDung($loai, $id);

            if ($noiDung instanceof CommunityPost) {
                $noiDung->forceFill(['hidden_at' => $noiDung->hidden_at ?? now(), 'hidden_reason' => $lyDo])->save();
                app(\App\Services\Notification\NotificationCenter::class)->baiBiAn($noiDung, $lyDo);
            } elseif ($noiDung instanceof CommunityComment) {
                $noiDung->forceFill(['hidden_at' => $noiDung->hidden_at ?? now()])->save();
            }

            $this->dong($nguoiXuLy, $loai, $id, CommunityReportStatus::DaAn);
        });

        $this->audit->log('goc-cay.an-sau-bao-cao', sprintf('Ẩn %s #%d sau báo cáo: %s', $loai === CommunityReport::BAI ? 'bài' : 'bình luận', $id, $lyDo));
    }

    public function boQua(User $nguoiXuLy, string $loai, int $id): void
    {
        $this->dong($nguoiXuLy, $loai, $id, CommunityReportStatus::BoQua);

        $this->audit->log('goc-cay.bao-cao-khong-vi-pham', sprintf('Báo cáo %s #%d: không vi phạm', $loai === CommunityReport::BAI ? 'bài' : 'bình luận', $id));
    }

    public function donCuaBai(CommunityPost $post): void
    {
        CommunityReport::where('target_type', CommunityReport::BAI)->where('target_id', $post->id)->delete();
        CommunityReport::where('target_type', CommunityReport::BINH_LUAN)
            ->whereIn('target_id', CommunityComment::where('community_post_id', $post->id)->select('id'))
            ->delete();
    }

    public function donCuaBinhLuan(CommunityComment $binhLuan): void
    {
        CommunityReport::where('target_type', CommunityReport::BINH_LUAN)
            ->whereIn('target_id', CommunityComment::where('parent_id', $binhLuan->id)->select('id')->union(DB::query()->selectRaw('?', [$binhLuan->id])))
            ->delete();
    }

    private function dong(User $nguoiXuLy, string $loai, int $id, CommunityReportStatus $ketQua): void
    {
        CommunityReport::pending()->where('target_type', $loai)->where('target_id', $id)->update([
            'status' => $ketQua->value,
            'handled_by' => $nguoiXuLy->id,
            'handled_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function noiDungDangHien(string $loai, int $id): CommunityPost|CommunityComment|null
    {
        if ($loai === CommunityReport::BAI) {
            return CommunityPost::approved()->find($id);
        }

        if ($loai === CommunityReport::BINH_LUAN) {
            return CommunityComment::visible()->whereIn('community_post_id', CommunityPost::approved()->select('id'))->find($id);
        }

        return null;
    }

    private function timNoiDung(string $loai, int $id): CommunityPost|CommunityComment|null
    {
        return $loai === CommunityReport::BAI ? CommunityPost::find($id) : CommunityComment::find($id);
    }
}

<?php

namespace App\Services\Community;

use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Services\Media\ImageStore;
use App\Services\Media\VideoMetadataStripper;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Ảnh và video của bài Góc cây — MỘT CỬA để lưu và xoá. */
class CommunityMediaStore
{
    public const TOI_DA_TEP = 10;

    public const TOI_DA_VIDEO = 2;

    public const ANH_TOI_DA_KB = 6144;

    public const VIDEO_TOI_DA_KB = 30720;

    public const MIMETYPES = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm'];

    public function __construct(
        private readonly ImageStore $anh,
        private readonly VideoMetadataStripper $video,
    ) {
    }

    public static function laVideo(UploadedFile $tep): bool
    {
        return str_starts_with((string) $tep->getMimeType(), 'video/');
    }

    public static function quyTac(int $daCo = 0, int $videoDaCo = 0): array
    {
        return [
            'media' => ['nullable', 'array', function (string $o, mixed $tep, Closure $loi) use ($daCo, $videoDaCo) {
                $tep = array_filter((array) $tep, fn ($t) => $t instanceof UploadedFile);

                if (count($tep) + $daCo > self::TOI_DA_TEP) {
                    $loi('Mỗi bài tối đa ' . self::TOI_DA_TEP . ' ảnh / video.');
                }

                if (count(array_filter($tep, fn ($t) => self::laVideo($t))) + $videoDaCo > self::TOI_DA_VIDEO) {
                    $loi('Mỗi bài tối đa ' . self::TOI_DA_VIDEO . ' video.');
                }
            }],
            'media.*' => ['file', 'mimetypes:' . implode(',', self::MIMETYPES), function (string $o, mixed $tep, Closure $loi) {
                if (! $tep instanceof UploadedFile) {
                    return;
                }

                $kb = $tep->getSize() / 1024;

                if (self::laVideo($tep) && $kb > self::VIDEO_TOI_DA_KB) {
                    $loi('Video tối đa ' . intdiv(self::VIDEO_TOI_DA_KB, 1024) . 'MB.');
                } elseif (! self::laVideo($tep) && $kb > self::ANH_TOI_DA_KB) {
                    $loi('Ảnh tối đa ' . intdiv(self::ANH_TOI_DA_KB, 1024) . 'MB.');
                }
            }],
        ];
    }

    public function them(CommunityPost $post, array $tep): void
    {
        $thuTu = (int) ($post->media()->max('sort_order') ?? -1) + 1;

        foreach ($tep as $f) {
            if (self::laVideo($f)) {
                $duongDan = $f->store('community/video', 'public');

                try {
                    $this->video->tuoc($duongDan);
                } catch (\Throwable $e) {
                    Log::error('KHÔNG XOÁ ĐƯỢC VỊ TRÍ của video vừa tải lên', ['path' => $duongDan, 'loi' => $e->getMessage()]);
                }

                $loai = 'video';
            } else {
                $duongDan = $this->anh->luu($f, 'community');
                $loai = 'image';
            }

            (new CommunityPostMedia())->forceFill([
                'community_post_id' => $post->id,
                'kind' => $loai,
                'path' => $duongDan,
                'sort_order' => $thuTu++,
            ])->save();
        }
    }

    public function xoa(CommunityPostMedia $m): void
    {
        $m->laVideo()
            ? Storage::disk('public')->delete($m->path)
            : $this->anh->xoa($m->path);

        $m->delete();
    }

    public function xoaCuaBai(CommunityPost $post): void
    {
        foreach ($post->media()->get() as $m) {
            $this->xoa($m);
        }
    }
}

<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/** Ghi nhật ký thao tác quản trị. */
class ActivityLogger
{
    public function log(
        string $action,
        string $description,
        ?Model $subject = null,
        array $properties = [],
    ): void {
        try {
            $nguoiLam = Auth::user();

            ActivityLog::create([
                'user_id' => $nguoiLam?->id,
                'actor_name' => $nguoiLam?->name,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'description' => mb_substr($description, 0, 500),
                'properties' => $properties === [] ? null : $properties,
                'ip_address' => Request::ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Không ghi được nhật ký thao tác.', [
                'action' => $action,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function logChange(
        string $action,
        Model $subject,
        string $doiTuong,
        string $truoc,
        string $sau,
        array $properties = [],
    ): void {
        $this->log(
            $action,
            sprintf('%s: %s → %s', $doiTuong, $truoc, $sau),
            $subject,
            array_merge(['truoc' => $truoc, 'sau' => $sau], $properties),
        );
    }
}

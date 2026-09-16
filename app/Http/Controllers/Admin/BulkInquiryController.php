<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInquiryStatusRequest;
use App\Models\BulkOrderInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BulkInquiryController extends Controller
{
    public function index(Request $request): View
    {
        $inquiries = BulkOrderInquiry::query()
            ->with('product')
            ->when($request->filled('q'), function ($query) use ($request) {
                $tu = trim((string) $request->query('q'));

                $query->where(function ($q) use ($tu) {
                    $q->where('contact_name', 'like', '%'.$tu.'%')
                        ->orWhere('contact_phone', 'like', '%'.$tu.'%')
                        ->orWhere('contact_email', 'like', '%'.$tu.'%');
                });
            })

            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status'))
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statuses = InquiryStatus::cases();

        return view('admin.bulk-inquiries.index', compact('inquiries', 'statuses'));
    }

    public function show(BulkOrderInquiry $bulkInquiry): View
    {
        $bulkInquiry->load(['product', 'handledBy']);

        $statuses = InquiryStatus::cases();

        return view('admin.bulk-inquiries.show', compact('bulkInquiry', 'statuses'));
    }

    public function updateStatus(
        UpdateInquiryStatusRequest $request,
        BulkOrderInquiry $bulkInquiry
    ): RedirectResponse {
        $data = $request->validated();

        $bulkInquiry->update([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? $bulkInquiry->admin_notes,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        return redirect()
            ->route('admin.bulk-inquiries.show', $bulkInquiry)
            ->with('success', 'Đã cập nhật trạng thái yêu cầu.');
    }
}

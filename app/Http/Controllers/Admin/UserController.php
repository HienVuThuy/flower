<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\LogsAdminActivity;
use App\Http\Controllers\Admin\Concerns\SortsAdminList;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use App\Services\Auth\AccountDeleter;
use App\Services\Auth\AccountDeletionException;
use Illuminate\View\View;

/** Quản lý khách hàng và quản trị viên. */
class UserController extends Controller
{
    use LogsAdminActivity;
    use SortsAdminList;

    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $tu = trim((string) $request->query('q'));

                $query->where(function ($q) use ($tu) {
                    $q->where('name', 'like', '%'.$tu.'%')
                        ->orWhere('email', 'like', '%'.$tu.'%');
                });
            })

            ->when(
                $request->filled('role'),
                fn ($q) => $q->where('role', $request->string('role'))
            )

            ->when(
                $request->query('xac_thuc') === 'chua',
                fn ($q) => $q->whereNull('email_verified_at')
            )
            ->when(
                $request->query('xac_thuc') === 'roi',
                fn ($q) => $q->whereNotNull('email_verified_at')
            )

            ->withCount(['orders as completed_orders_count' => fn ($q) => $q->where('status', 'completed')])
            ->withSum(['orders as spent_total' => fn ($q) => $q->where('status', 'completed')], 'grand_total')

            ->tap(fn ($q) => $this->applySort($q, $request, [
                'ten' => 'name',
                'email' => 'email',
                'so-don' => 'completed_orders_count',
                'da-chi' => 'spent_total',
                'ngay' => 'created_at',
            ], fn ($q) => $q->latest()))

            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $daGiao = fn ($q) => $q->where('status', \App\Enums\OrderStatus::Completed->value);

        $user->loadCount([
            'orders',
            'orders as completed_orders_count' => $daGiao,
            'orders as cancelled_orders_count' => fn ($q) => $q->where('status', \App\Enums\OrderStatus::Cancelled->value),
            'reviews',
        ])->loadSum(['orders as spent_total' => $daGiao], 'grand_total');

        return view('admin.users.show', [
            'user' => $user,
            'donGanNhat' => $user->orders()->latest('created_at')->value('created_at'),
            'diaChi' => $user->addresses()->orderByDesc('is_default')->orderBy('id')->get(),
            'don' => $user->orders()
                ->withCount('items')
                ->latest('created_at')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(UserRole::class)],
        ], [], ['role' => 'vai trò']);

        $moi = UserRole::from($data['role']);
        $cu = $user->role;

        if ($moi === $cu) {
            return back()->with('info', 'Vai trò không thay đổi.');
        }

        if ($loi = $this->loiDoiVaiTro($user, $moi)) {
            return back()->with('error', $loi);
        }

        $user->role = $moi;
        $user->save();

        $this->audit()->logChange(
            'user.role_changed',
            $user,
            'Vai trò của '.$user->name,
            $cu->label(),
            $moi->label(),
            ['email' => $user->email],
        );

        return back()->with(
            'success',
            sprintf('Đã đổi vai trò của %s thành "%s".', $user->name, $moi->label()),
        );
    }

    public function updateLock(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'khoa' => ['required', 'boolean'],
            'ly_do' => ['nullable', 'string', 'max:255'],
        ], [], ['ly_do' => 'lý do']);

        $khoa = (bool) $data['khoa'];

        if ($khoa === $user->isLocked()) {
            return back()->with('info', 'Trạng thái tài khoản không thay đổi.');
        }

        if ($khoa && $user->is(Auth::user())) {
            return back()->with('error', 'Không tự khoá tài khoản của chính mình được.');
        }

        if ($khoa && $user->isAdmin() && $this->adminConLaiNeuBo($user) === 0) {
            return back()->with(
                'error',
                'Đây là quản trị viên duy nhất còn hoạt động, không khoá được.',
            );
        }

        $user->locked_at = $khoa ? now() : null;

        $user->lock_reason = $khoa ? ($data['ly_do'] ?? null) : null;
        $user->save();

        $this->audit()->log(
            $khoa ? 'user.locked' : 'user.unlocked',
            sprintf('%s tài khoản %s', $khoa ? 'Khoá' : 'Mở khoá', $user->name),
            $user,
            array_filter([
                'email' => $user->email,
                'ly_do' => $khoa ? ($data['ly_do'] ?? null) : null,
            ]),
        );

        return back()->with('success', $khoa
            ? 'Đã khoá tài khoản '.$user->name.'. Họ sẽ bị đăng xuất ngay.'
            : 'Đã mở khoá tài khoản '.$user->name.'.');
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ], [], ['name' => 'tên', 'password' => 'mật khẩu', 'role' => 'vai trò']);

        $user = new User();
        $user->forceFill([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'role' => UserRole::from($data['role']),
            'email_verified_at' => now(),
        ])->save();

        $this->audit()->log('user.created', 'Tạo tài khoản ' . $user->name, $user, [
            'email' => $user->email,
            'vai_tro' => $user->role->label(),
        ]);

        return redirect()->route('admin.users.show', $user)->with('success', 'Đã tạo tài khoản ' . $user->name . '.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
        ], [], ['name' => 'tên', 'role' => 'vai trò']);

        $vaiTroMoi = UserRole::from($data['role']);

        if ($vaiTroMoi !== $user->role && ($loi = $this->loiDoiVaiTro($user, $vaiTroMoi))) {
            return back()->withInput()->with('error', $loi);
        }

        $truoc = ['ten' => $user->name, 'email' => $user->email, 'vai_tro' => $user->role->label()];
        $emailMoi = mb_strtolower($data['email']);

        $user->forceFill([
            'name' => $data['name'],
            'email' => $emailMoi,
            'role' => $vaiTroMoi,
            'email_verified_at' => $emailMoi === $user->email ? $user->email_verified_at : null,
        ])->save();

        $this->audit()->log('user.updated', 'Sửa tài khoản ' . $user->name, $user, [
            'truoc' => $truoc,
            'sau' => ['ten' => $user->name, 'email' => $user->email, 'vai_tro' => $user->role->label()],
        ]);

        return redirect()->route('admin.users.show', $user)->with('success', 'Đã cập nhật tài khoản ' . $user->name . '.');
    }

    public function destroy(User $user, AccountDeleter $xoa): RedirectResponse
    {
        if ($user->is(Auth::user())) {
            return back()->with('error', 'Không tự xoá tài khoản của chính mình ở đây.');
        }

        $ten = $user->name;
        $email = $user->email;

        try {
            $xoa->delete($user);
        } catch (AccountDeletionException $e) {
            return back()->with('error', str_replace('Bạn còn', 'Tài khoản này còn', $e->getMessage()) . ' Có thể khoá tài khoản thay vì xoá.');
        }

        $this->audit()->log('user.deleted', 'Xoá tài khoản ' . $ten, null, ['email' => $email]);

        return redirect()->route('admin.users.index')->with('success', 'Đã xoá tài khoản ' . $ten . '. Đơn hàng cũ của họ vẫn được giữ.');
    }

    private function loiDoiVaiTro(User $user, UserRole $moi): ?string
    {
        if ($user->is(Auth::user())) {
            return 'Không tự đổi vai trò của chính mình được. Hãy nhờ một quản trị viên khác.';
        }

        if ($user->role === UserRole::Admin && $moi !== UserRole::Admin && $this->adminConLaiNeuBo($user) === 0) {
            return 'Đây là quản trị viên duy nhất còn hoạt động. '
                .'Hãy chỉ định một quản trị viên khác trước khi hạ quyền tài khoản này.';
        }

        return null;
    }

    private function adminConLaiNeuBo(User $user): int
    {
        return User::where('role', UserRole::Admin)
            ->whereNull('locked_at')
            ->whereKeyNot($user->getKey())
            ->count();
    }
}

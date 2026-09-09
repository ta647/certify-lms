<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Http\Requests\QaBoard\IndexRequest;
use App\Http\Requests\QaBoard\StoreThreadRequest;
use App\Http\Requests\QaBoard\UpdateThreadRequest;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaBoard\ResolveThreadAction;
use App\UseCases\QaBoard\StoreThreadAction;
use App\UseCases\QaBoard\UnresolveThreadAction;
use App\UseCases\QaBoard\UpdateThreadAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * qa-board (質問掲示板) Controller。受講生 / コーチ / admin 共通で利用される。
 *
 * - index / show: 公開ルート(qa-board.*) と管理者モデレーションルート(admin.qa-board.*)を共用し、
 *   `routeIs('admin.*')` で表示範囲を切り替える(admin は公開停止中資格も含め横断閲覧)。
 * - create / store / edit / update / resolve / unresolve: 受講生(投稿者本人)専用。
 * - destroy: 投稿者本人 または admin(モデレーション)のいずれも同じメソッドを通り、認可は Policy::delete に委譲する。
 */
class QaThreadController extends Controller
{
    public function index(IndexRequest $request): View
    {
        $viewer = $request->user();
        $filters = $request->filters();
        $isAdminContext = $request->routeIs('admin.*');

        $query = QaThread::query()->withCount('replies')->with(['certification', 'user']);

        if (! $isAdminContext) {
            $query->visibleTo($viewer);
        }

        $threads = $query->filter($filters)->latest()->paginate(20)->withQueryString();

        return view('qa-thread.index', [
            'threads' => $threads,
            'filters' => $filters,
            'certifications' => $this->certificationOptions($viewer, $isAdminContext),
            'publishedStatus' => CertificationStatus::Published,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', QaThread::class);

        return view('qa-thread.create', [
            'certifications' => Certification::query()->published()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreThreadRequest $request, StoreThreadAction $action): RedirectResponse
    {
        $thread = $action($request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を投稿しました。');
    }

    public function show(QaThread $thread): View
    {
        $this->authorize('view', $thread);

        $thread->load(['certification', 'user', 'replies.user']);

        return view('qa-thread.show', ['thread' => $thread]);
    }

    public function edit(QaThread $thread): View
    {
        $this->authorize('update', $thread);

        return view('qa-thread.edit', ['thread' => $thread]);
    }

    public function update(UpdateThreadRequest $request, QaThread $thread, UpdateThreadAction $action): RedirectResponse
    {
        $action($thread, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '質問を更新しました。');
    }

    public function destroy(QaThread $thread): RedirectResponse
    {
        $this->authorize('delete', $thread);

        $isAdminContext = request()->routeIs('admin.*');
        $thread->delete();

        return redirect()
            ->route($isAdminContext ? 'admin.qa-board.index' : 'qa-board.index')
            ->with('success', '質問を削除しました。');
    }

    public function resolve(QaThread $thread, ResolveThreadAction $action): RedirectResponse
    {
        $this->authorize('resolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '解決済にしました。');
    }

    public function unresolve(QaThread $thread, UnresolveThreadAction $action): RedirectResponse
    {
        $this->authorize('unresolve', $thread);

        $action($thread);

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '未解決に戻しました。');
    }

    /**
     * 一覧の絞り込みチップに表示する資格一覧。admin は全件、coach は担当資格のみ、student は公開中全件。
     *
     * @return Collection<int, Certification>
     */
    private function certificationOptions(User $viewer, bool $isAdminContext): Collection
    {
        if ($isAdminContext) {
            return Certification::query()->orderBy('name')->get();
        }

        if ($viewer->role === UserRole::Coach) {
            return Certification::query()->published()->assignedTo($viewer)->orderBy('name')->get();
        }

        return Certification::query()->published()->orderBy('name')->get();
    }
}

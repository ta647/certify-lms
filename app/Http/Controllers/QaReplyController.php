<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\QaBoard\StoreReplyRequest;
use App\Http\Requests\QaBoard\UpdateReplyRequest;
use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaBoard\StoreReplyAction;
use App\UseCases\QaBoard\UpdateReplyAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * QaThread に対する回答(QaReply)の Controller。
 *
 * `{thread}/replies/{reply}` は Laravel の暗黙バインディングだけでは親子関係を検証しないため、
 * 対象が存在する edit / update / destroy では冒頭で $reply が $thread に属することを明示的に確認する。
 */
class QaReplyController extends Controller
{
    public function store(StoreReplyRequest $request, QaThread $thread, StoreReplyAction $action): RedirectResponse
    {
        $action($thread, $request->user(), $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を投稿しました。');
    }

    public function edit(QaThread $thread, QaReply $reply): View
    {
        $this->ensureBelongsToThread($thread, $reply);
        $this->authorize('update', $reply);

        return view('qa-thread.reply-edit', ['thread' => $thread, 'reply' => $reply]);
    }

    public function update(UpdateReplyRequest $request, QaThread $thread, QaReply $reply, UpdateReplyAction $action): RedirectResponse
    {
        $this->ensureBelongsToThread($thread, $reply);

        $action($reply, $request->validated());

        return redirect()
            ->route('qa-board.show', $thread)
            ->with('success', '回答を更新しました。');
    }

    public function destroy(QaThread $thread, QaReply $reply): RedirectResponse
    {
        $this->ensureBelongsToThread($thread, $reply);
        $this->authorize('delete', $reply);

        $isAdminContext = request()->routeIs('admin.*');
        $reply->delete();

        return redirect()
            ->route($isAdminContext ? 'admin.qa-board.show' : 'qa-board.show', $thread)
            ->with('success', '回答を削除しました。');
    }

    private function ensureBelongsToThread(QaThread $thread, QaReply $reply): void
    {
        abort_unless($reply->qa_thread_id === $thread->id, 404);
    }
}

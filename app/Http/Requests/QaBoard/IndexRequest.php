<?php

declare(strict_types=1);

namespace App\Http\Requests\QaBoard;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

/**
 * qa-board 一覧アクセスの入力検証。受講生 / コーチ / admin(モデレーション) 共通で利用される。
 *
 * 取得範囲は role に応じて QaThread::scopeVisibleTo(Controller 側) で絞り込むため、
 * ここでは role が qa-board を利用できる 3 ロールのいずれかであることのみ確認する。
 */
class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->role;

        return $role === UserRole::Student
            || $role === UserRole::Coach
            || $role === UserRole::Admin;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:unresolved,resolved'],
            'certification_id' => ['nullable', 'ulid', 'exists:certifications,id'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array{status: ?string, certification_id: ?string, keyword: ?string}
     */
    public function filters(): array
    {
        return [
            'status' => $this->input('status'),
            'certification_id' => $this->input('certification_id'),
            'keyword' => $this->input('keyword'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\Learning\ProgressSummary;
use App\Services\ProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class ProgressServiceTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    private function makeEnrollment(Certification $certification): Enrollment
    {
        $student = User::factory()->student()->create();

        return Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
    }

    public function test_summarize_returns_zero_ratios_when_no_published_content(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->makeEnrollment($certification);

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertInstanceOf(ProgressSummary::class, $summary);
        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);
        $this->assertSame(0.0, $summary->partCompletionRatio);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_counts_full_completion_across_all_levels(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->makeEnrollment($certification);
        [$part, $chapter, $section] = $this->makePartChain($certification);

        SectionProgress::factory()->forEnrollment($enrollment)->forSection($section)->create();

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(1.0, $summary->sectionCompletionRatio);
        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(1.0, $summary->chapterCompletionRatio);
        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(1.0, $summary->partCompletionRatio);
        $this->assertSame(1.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_treats_chapter_complete_only_when_all_its_sections_done(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->makeEnrollment($certification);

        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapterA = Chapter::factory()->forPart($part)->published()->create();
        $sectionA1 = Section::factory()->forChapter($chapterA)->published()->create();
        Section::factory()->forChapter($chapterA)->published()->create(); // chapterA の 2 件目(未読了のまま残す)
        $chapterB = Chapter::factory()->forPart($part)->published()->create();
        $sectionB1 = Section::factory()->forChapter($chapterB)->published()->create();

        // chapterA: 2 Section 中 1 件のみ読了 → chapterA は未完了
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($sectionA1)->create();
        // chapterB: 唯一の Section を読了 → chapterB は完了
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($sectionB1)->create();

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(3, $summary->sectionsTotal);
        $this->assertSame(2, $summary->sectionsCompleted);
        $this->assertSame(2, $summary->chaptersTotal);
        $this->assertSame(1, $summary->chaptersCompleted, 'chapterA は未完了、chapterB のみ完了のはず');
        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted, '配下の chapterA が未完了なので Part も未完了のはず');
    }

    public function test_summarize_excludes_draft_content(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->makeEnrollment($certification);
        $this->makePartChain($certification); // 公開済 1 件
        $this->makePartChain($certification, 'draft'); // draft 1 件(集計対象外)

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(1, $summary->partsTotal);
    }

    public function test_summarize_only_counts_own_enrollments_progress(): void
    {
        $certification = Certification::factory()->published()->create();
        $enrollment = $this->makeEnrollment($certification);
        $otherEnrollment = $this->makeEnrollment($certification);
        [, , $section] = $this->makePartChain($certification);

        // 他 Enrollment の読了は対象 Enrollment の集計に混ざらないはず
        SectionProgress::factory()->forEnrollment($otherEnrollment)->forSection($section)->create();

        $summary = app(ProgressService::class)->summarize($enrollment);

        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
    }

    public function test_batch_section_completion_ratio_returns_empty_array_for_empty_collection(): void
    {
        $result = app(ProgressService::class)->batchSectionCompletionRatio(collect());

        $this->assertSame([], $result);
    }

    public function test_batch_section_completion_ratio_matches_single_enrollment_calculation(): void
    {
        $certification = Certification::factory()->published()->create();
        [, , $section1] = $this->makePartChain($certification);
        $part = Part::factory()->forCertification($certification)->published()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();
        $section2 = Section::factory()->forChapter($chapter)->published()->create();

        $fullyDone = $this->makeEnrollment($certification);
        SectionProgress::factory()->forEnrollment($fullyDone)->forSection($section1)->create();
        SectionProgress::factory()->forEnrollment($fullyDone)->forSection($section2)->create();

        $halfDone = $this->makeEnrollment($certification);
        SectionProgress::factory()->forEnrollment($halfDone)->forSection($section1)->create();

        $untouched = $this->makeEnrollment($certification);

        $enrollments = Enrollment::query()->whereIn('id', [$fullyDone->id, $halfDone->id, $untouched->id])->get();
        $result = app(ProgressService::class)->batchSectionCompletionRatio($enrollments);

        $this->assertSame(1.0, $result[$fullyDone->id]);
        $this->assertSame(0.5, $result[$halfDone->id]);
        $this->assertSame(0.0, $result[$untouched->id]);
    }
}

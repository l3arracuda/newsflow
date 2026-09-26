<?php

namespace App\Http\Controllers\Admin;

use App\Models\WorkflowRun;
use App\Support\SafeErrorPresenter;
use App\Workflows\WorkflowRetrier;
use App\Workflows\WorkflowStepCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class WorkflowRunController
{
    public function show(WorkflowRun $workflow, SafeErrorPresenter $errors): View
    {
        $workflow->load(['article.source', 'steps' => fn ($query) => $query->orderBy('id')]);
        $workflow->setAttribute('safe_error_summary', $errors->sanitize($workflow->error_summary));
        foreach ($workflow->steps as $step) {
            $step->setAttribute('safe_error_summary', $errors->sanitize($step->error_summary));
            $metadata = $step->metadata ?? [];
            $result = match ($step->step_key) {
                'extract_facts' => $metadata['facts'] ?? [],
                'summarize' => $metadata['result'] ?? [],
                'rewrite' => $metadata['rewrite'] ?? [],
                'fact_check' => $metadata,
                default => [],
            };
            $provenance = $result['_provenance'] ?? [];
            unset($result['_provenance'], $result['source_claims']);
            $step->setAttribute('ai_provenance', $provenance);
            $step->setAttribute('ai_output', $result);
        }
        $failedSteps = $workflow->steps
            ->groupBy('step_key')
            ->map(fn ($steps) => $steps->last())
            ->filter(fn ($step) => $step->status->value === 'failed')
            ->values();

        return view('workflows.show', ['workflow' => $workflow, 'failedSteps' => $failedSteps]);
    }

    public function retry(Request $request, WorkflowRun $workflow, WorkflowRetrier $retrier): RedirectResponse
    {
        $input = $request->validate([
            'step_key' => ['nullable', 'string', Rule::in(array_column(WorkflowStepCatalog::STEPS, 'key'))],
        ]);
        try {
            $retrier->retry($workflow->id, $input['step_key'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['retry' => $exception->getMessage()]);
        }

        return redirect()->route('workflows.show', $workflow)->with('status', 'ส่งคำขอลอง workflow อีกครั้งแล้ว');
    }
}

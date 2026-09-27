<?php

namespace App\AI;

class FactCheckReconciler
{
    public function __construct(private readonly FactEvidenceValidator $evidence) {}

    public function reconcile(array $facts, array $draft, array $result): array
    {
        $draftText = trim(($draft['title'] ?? '')."\n".($draft['body'] ?? ''));
        $mismatches = [];
        $unsupported = [];
        $warnings = [];
        $resolvedQuantityQuotes = [];
        $sourceQuantities = array_values(array_filter($facts['quantitative_claims'] ?? [], fn ($claim) => is_array($claim) && ($claim['evidence_verified'] ?? false)));

        foreach ($result['quantitative_claims'] ?? [] as $claim) {
            if (! is_array($claim) || ! $this->draftContains($draftText, $claim['draft_quote'] ?? '')) {
                if (is_array($claim) && trim((string) ($claim['draft_quote'] ?? '')) !== '') {
                    $warnings[] = 'ตัวตรวจพบข้อความตัวเลขที่ไม่ตรงกับข้อความในร่าง จึงไม่นำมาใช้ตัดสิน';
                }

                continue;
            }

            $match = collect($sourceQuantities)->first(fn (array $source) => $this->canonicalSubject($source['subject'] ?? '') === $this->canonicalSubject($claim['subject'] ?? '')
                && $this->canonicalRelation($source['relation'] ?? '') === $this->canonicalRelation($claim['relation'] ?? '')
            );

            if ($match && $this->canonicalValue($match['value'] ?? '') === $this->canonicalValue($claim['value'] ?? '')
                && $this->canonicalUnit($match['unit'] ?? '') === $this->canonicalUnit($claim['unit'] ?? '')) {
                $resolvedQuantityQuotes[] = $claim['draft_quote'];

                continue;
            }

            if ($match) {
                $mismatches[] = [
                    'draft_quote' => $claim['draft_quote'],
                    'source_quote' => $match['source_quote'],
                    'explanation' => 'ประเภทและรายการตรงกัน แต่จำนวนหรือหน่วยไม่ตรงกับหลักฐานต้นทาง',
                ];
            } else {
                $unsupported[] = [
                    'draft_quote' => $claim['draft_quote'],
                    'explanation' => 'ไม่พบข้อเท็จจริงตัวเลขที่มีหลักฐานต้นทางตรงกับรายการนี้',
                ];
            }
        }

        foreach ($result['mismatches'] ?? [] as $finding) {
            $finding = $this->normalizeFinding($finding);
            if (! $finding || ! $this->draftContains($draftText, $finding['draft_quote'])) {
                if ($finding) {
                    $warnings[] = 'ตัวตรวจระบุข้อความที่ไม่พบในร่าง จึงไม่นำมาใช้ตัดสิน';
                }

                continue;
            }
            if ($this->overlapsResolvedQuantity($finding['draft_quote'], $resolvedQuantityQuotes)) {
                continue;
            }

            if (! $this->hasVerifiedEvidence($facts, $finding['source_quote'])) {
                $unsupported[] = [
                    'draft_quote' => $finding['draft_quote'],
                    'explanation' => 'ตัวตรวจไม่ได้แนบข้อความหลักฐานที่ยืนยันกลับไปยังข่าวต้นทางได้',
                ];

                continue;
            }

            $mismatches[] = $finding;
        }

        foreach ($result['unsupported_claims'] ?? [] as $finding) {
            $finding = $this->normalizeFinding($finding, requireEvidence: false);
            if (! $finding || ! $this->draftContains($draftText, $finding['draft_quote'])) {
                if ($finding) {
                    $warnings[] = 'ตัวตรวจระบุข้อความที่ไม่พบในร่าง จึงไม่นำมาใช้ตัดสิน';
                }

                continue;
            }
            if ($this->overlapsResolvedQuantity($finding['draft_quote'], $resolvedQuantityQuotes)) {
                continue;
            }

            $unsupported[] = [
                'draft_quote' => $finding['draft_quote'],
                'explanation' => $finding['explanation'],
            ];
        }

        $result['mismatches'] = $this->uniqueFindings($mismatches);
        $result['unsupported_claims'] = $this->uniqueFindings($unsupported);
        $result['review_warnings'] = array_values(array_unique($warnings));
        $result['verified_quantitative_claims'] = count($resolvedQuantityQuotes);
        $result['pass'] = $result['mismatches'] === [] && $result['unsupported_claims'] === [];
        $result['severity'] = $result['pass'] ? 'none' : (($result['severity'] ?? 'none') === 'none' ? 'medium' : $result['severity']);

        return $result;
    }

    private function normalizeFinding(mixed $finding, bool $requireEvidence = true): ?array
    {
        if (is_string($finding)) {
            return trim($finding) === '' ? null : ['draft_quote' => trim($finding), 'source_quote' => '', 'explanation' => 'AI ระบุข้อสงสัยนี้ แต่ไม่มีรายละเอียดหลักฐาน'];
        }
        if (! is_array($finding) || trim((string) ($finding['draft_quote'] ?? '')) === '') {
            return null;
        }

        return [
            'draft_quote' => trim((string) $finding['draft_quote']),
            'source_quote' => trim((string) ($finding['source_quote'] ?? '')),
            'explanation' => trim((string) ($finding['explanation'] ?? '')) ?: ($requireEvidence ? 'ข้อความอาจไม่สอดคล้องกับหลักฐานต้นทาง' : 'ไม่พบหลักฐานรองรับข้อความนี้'),
        ];
    }

    private function hasVerifiedEvidence(array $facts, string $quote): bool
    {
        if ($quote === '') {
            return false;
        }

        foreach ($facts['evidence_claims'] ?? [] as $claim) {
            if (($claim['evidence_verified'] ?? false) && $this->sameText($claim['source_quote'] ?? '', $quote)) {
                return true;
            }
        }
        foreach ($facts['quantitative_claims'] ?? [] as $claim) {
            if (($claim['evidence_verified'] ?? false) && $this->sameText($claim['source_quote'] ?? '', $quote)) {
                return true;
            }
        }

        return false;
    }

    private function draftContains(string $draft, string $quote): bool
    {
        return $quote !== '' && $this->evidence->contains($draft, $quote);
    }

    private function sameText(string $left, string $right): bool
    {
        return $this->canonicalLabel($left) === $this->canonicalLabel($right);
    }

    private function canonicalSubject(string $value): string
    {
        $value = str_replace(['ทองคำแท่ง', 'ทองแท่ง'], 'ทองแท่ง', $value);

        return $this->canonicalLabel($value);
    }

    private function canonicalRelation(string $value): string
    {
        if (in_array(mb_strtolower(trim($value)), ['buy', 'purchase'], true) || str_contains($value, 'รับซื้อ')) {
            return 'buy';
        }
        if (in_array(mb_strtolower(trim($value)), ['sell', 'selling'], true) || str_contains($value, 'ขายออก')) {
            return 'sell';
        }

        return $this->canonicalLabel($value);
    }

    private function canonicalLabel(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/[\p{Z}\s\p{P}\p{S}]+/u', '', $value) ?? $value;
    }

    private function canonicalValue(string $value): string
    {
        $value = strtr($value, ['๐' => '0', '๑' => '1', '๒' => '2', '๓' => '3', '๔' => '4', '๕' => '5', '๖' => '6', '๗' => '7', '๘' => '8', '๙' => '9']);

        return preg_replace('/[^0-9.\-]/', '', $value) ?? '';
    }

    private function canonicalUnit(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return in_array($value, ['thb', 'baht', 'บาท'], true) ? 'บาท' : $this->canonicalLabel($value);
    }

    private function overlapsResolvedQuantity(string $quote, array $resolvedQuotes): bool
    {
        foreach ($resolvedQuotes as $resolved) {
            if ($this->sameText($quote, $resolved) || $this->evidence->contains($quote, $resolved)) {
                return true;
            }
        }

        return false;
    }

    private function uniqueFindings(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding) {
            $key = $this->canonicalLabel($finding['draft_quote']);
            $unique[$key] ??= $finding;
        }

        return array_values($unique);
    }
}

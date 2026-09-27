<?php

namespace Tests\Unit;

use App\AI\FactCheckReconciler;
use App\AI\FactEvidenceValidator;
use PHPUnit\Framework\TestCase;

class FactCheckReconcilerTest extends TestCase
{
    public function test_gold_price_claims_matching_typed_source_facts_pass_and_phantom_ai_flag_is_ignored(): void
    {
        $validator = new FactEvidenceValidator;
        $sourceText = 'สมาคมค้าทองคำประกาศราคา ทองคำแท่ง 96.5% รับซื้อ 67,650 บาท และขายออก 67,850 บาท ส่วนทองรูปพรรณ 96.5% รับซื้อ 66,294.68 บาท และขายออก 68,650 บาท';
        $facts = $validator->validate([
            'quantitative_claims' => [
                ['subject' => 'ทองคำแท่ง 96.5%', 'relation' => 'รับซื้อ', 'value' => '67,650', 'unit' => 'บาท', 'source_quote' => 'ทองคำแท่ง 96.5% รับซื้อ 67,650 บาท'],
                ['subject' => 'ทองคำแท่ง 96.5%', 'relation' => 'ขายออก', 'value' => '67,850', 'unit' => 'บาท', 'source_quote' => 'ทองคำแท่ง 96.5% รับซื้อ 67,650 บาท และขายออก 67,850 บาท'],
                ['subject' => 'ทองรูปพรรณ 96.5%', 'relation' => 'รับซื้อ', 'value' => '66,294.68', 'unit' => 'บาท', 'source_quote' => 'ทองรูปพรรณ 96.5% รับซื้อ 66,294.68 บาท'],
                ['subject' => 'ทองรูปพรรณ 96.5%', 'relation' => 'ขายออก', 'value' => '68,650', 'unit' => 'บาท', 'source_quote' => 'ทองรูปพรรณ 96.5% รับซื้อ 66,294.68 บาท และขายออก 68,650 บาท'],
            ],
            'evidence_claims' => [],
        ], $sourceText);

        $draft = [
            'title' => 'ราคาทองคำประจำวันที่ 27 กันยายน 2569',
            'body' => 'ราคาทองคำแท่ง 96.5% รับซื้อที่ 67,650 บาท และขายออกที่ 67,850 บาท ส่วนทองรูปพรรณ 96.5% รับซื้อที่ 66,294.68 บาท และขายออกที่ 68,650 บาท',
        ];
        $result = (new FactCheckReconciler($validator))->reconcile($facts, $draft, [
            'pass' => false,
            'severity' => 'high',
            'mismatches' => [['draft_quote' => 'ทองคำแท่ง 96.5% ขายออกที่ 6', 'source_quote' => '', 'explanation' => 'ราคาไม่ครบ']],
            'unsupported_claims' => [
                ['draft_quote' => 'ราคาทองคำแท่ง 96.5% รับซื้อที่ 67,650 บาท และขายออกที่ 67,850 บาท', 'explanation' => 'ไม่มีหลักฐาน'],
                ['draft_quote' => 'ทองรูปพรรณ 96.5% รับซื้อที่ 66,294.68 บาท และขายออกที่ 68,650 บาท', 'explanation' => 'ไม่มีหลักฐาน'],
            ],
            'quantitative_claims' => [
                ['draft_quote' => 'ทองคำแท่ง 96.5% รับซื้อที่ 67,650 บาท', 'subject' => 'ทองคำแท่ง 96.5%', 'relation' => 'รับซื้อ', 'value' => '67,650', 'unit' => 'บาท'],
                ['draft_quote' => 'ขายออกที่ 67,850 บาท', 'subject' => 'ทองคำแท่ง 96.5%', 'relation' => 'ขายออก', 'value' => '67,850', 'unit' => 'บาท'],
                ['draft_quote' => 'ทองรูปพรรณ 96.5% รับซื้อที่ 66,294.68 บาท', 'subject' => 'ทองรูปพรรณ 96.5%', 'relation' => 'รับซื้อ', 'value' => '66,294.68', 'unit' => 'บาท'],
                ['draft_quote' => 'ขายออกที่ 68,650 บาท', 'subject' => 'ทองรูปพรรณ 96.5%', 'relation' => 'ขายออก', 'value' => '68,650', 'unit' => 'บาท'],
            ],
        ]);

        $this->assertTrue($result['pass']);
        $this->assertSame('none', $result['severity']);
        $this->assertSame([], $result['mismatches']);
        $this->assertSame([], $result['unsupported_claims']);
        $this->assertSame(4, $result['verified_quantitative_claims']);
        $this->assertNotEmpty($result['review_warnings']);
    }

    public function test_wrong_price_is_reported_with_the_source_quote(): void
    {
        $facts = (new FactEvidenceValidator)->validate([
            'quantitative_claims' => [['subject' => 'ทองคำแท่ง 96.5%', 'relation' => 'ขายออก', 'value' => '67,850', 'unit' => 'บาท', 'source_quote' => 'ทองคำแท่ง 96.5% ขายออก 67,850 บาท']],
            'evidence_claims' => [],
        ], 'ทองคำแท่ง 96.5% ขายออก 67,850 บาท');

        $result = (new FactCheckReconciler(new FactEvidenceValidator))->reconcile($facts, ['title' => '', 'body' => 'ทองคำแท่ง 96.5% ขายออก 67,800 บาท'], [
            'pass' => true, 'severity' => 'none', 'mismatches' => [], 'unsupported_claims' => [],
            'quantitative_claims' => [['draft_quote' => 'ทองคำแท่ง 96.5% ขายออก 67,800 บาท', 'subject' => 'ทองคำแท่ง 96.5%', 'relation' => 'ขายออก', 'value' => '67,800', 'unit' => 'บาท']],
        ]);

        $this->assertFalse($result['pass']);
        $this->assertSame('ทองคำแท่ง 96.5% ขายออก 67,850 บาท', $result['mismatches'][0]['source_quote']);
        $this->assertSame('ทองคำแท่ง 96.5% ขายออก 67,800 บาท', $result['mismatches'][0]['draft_quote']);
    }

    public function test_extractor_quote_must_exist_in_the_source_before_it_can_support_a_claim(): void
    {
        $facts = (new FactEvidenceValidator)->validate([
            'quantitative_claims' => [['subject' => 'ทองคำแท่ง', 'relation' => 'ขายออก', 'value' => '99', 'unit' => 'บาท', 'source_quote' => 'ทองคำแท่งขายออก 99 บาท']],
            'evidence_claims' => [['statement' => 'ข้อความแต่งขึ้น', 'source_quote' => 'นี่ไม่ใช่ประโยคที่อยู่ในข่าว']],
        ], 'ข่าวระบุราคาทองคำแท่งขายออก 90 บาท');

        $this->assertFalse($facts['quantitative_claims'][0]['evidence_verified']);
        $this->assertFalse($facts['evidence_claims'][0]['evidence_verified']);
        $this->assertSame(FactEvidenceValidator::SCHEMA_VERSION, $facts['evidence_schema_version']);
    }
}

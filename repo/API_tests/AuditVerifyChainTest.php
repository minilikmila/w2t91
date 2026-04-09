<?php

namespace Tests\Feature;

use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditVerifyChainTest extends TestCase
{
    use RefreshDatabase;

    private AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = app(AuditService::class);
    }

    public function test_verify_empty_chain_is_intact(): void
    {
        $result = $this->auditService->verifyChain();

        $this->assertTrue($result['chain_intact']);
        $this->assertEquals(0, $result['total_events']);
    }

    public function test_verify_single_event_chain(): void
    {
        $this->auditService->log('created', 'Learner', 1, null, null, ['name' => 'John'], 'Test event');

        $result = $this->auditService->verifyChain();

        $this->assertTrue($result['chain_intact']);
        $this->assertEquals(1, $result['total_events']);
        $this->assertEquals(1, $result['valid_links']);
    }

    public function test_verify_multi_event_chain(): void
    {
        $this->auditService->log('created', 'Learner', 1, null, null, ['name' => 'John'], 'Created');
        $this->auditService->log('updated', 'Learner', 1, null, ['name' => 'John'], ['name' => 'Jane'], 'Updated');
        $this->auditService->log('soft_deleted', 'Learner', 1, null, ['name' => 'Jane'], null, 'Deleted');

        $result = $this->auditService->verifyChain();

        $this->assertTrue($result['chain_intact']);
        $this->assertEquals(3, $result['total_events']);
        $this->assertEquals(3, $result['valid_links']);
        $this->assertEquals(0, $result['invalid_links']);
    }

    public function test_tampered_event_content_detected(): void
    {
        $this->auditService->log('created', 'Learner', 1, null, null, ['name' => 'John'], 'Created');
        $event = $this->auditService->log('updated', 'Learner', 1, null, ['name' => 'John'], ['name' => 'Jane'], 'Updated');

        // Tamper with the event directly in DB (bypass immutability guards)
        \Illuminate\Support\Facades\DB::table('audit_events')
            ->where('id', $event->id)
            ->update(['description' => 'Tampered description']);

        $result = $this->auditService->verifyChain();

        $this->assertFalse($result['chain_intact']);
        $this->assertGreaterThan(0, $result['invalid_links']);
    }

    public function test_tampered_prior_hash_detected(): void
    {
        $this->auditService->log('created', 'Learner', 1, null, null, ['name' => 'John'], 'Created');
        $event = $this->auditService->log('updated', 'Learner', 1, null, ['name' => 'John'], ['name' => 'Jane'], 'Updated');

        // Tamper with the prior_hash directly in DB
        \Illuminate\Support\Facades\DB::table('audit_events')
            ->where('id', $event->id)
            ->update(['prior_hash' => 'aaaa' . substr($event->prior_hash, 4)]);

        $result = $this->auditService->verifyChain();

        $this->assertFalse($result['chain_intact']);
    }
}

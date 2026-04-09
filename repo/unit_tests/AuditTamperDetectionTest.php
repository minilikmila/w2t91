<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuditTamperDetectionTest extends TestCase
{
    /**
     * Simulate the hash computation used by AuditService::log().
     */
    private function computeEventHash(array $event, ?string $priorHash): string
    {
        $payload = json_encode([
            'event_type' => $event['event_type'],
            'entity_type' => $event['entity_type'],
            'entity_id' => $event['entity_id'],
            'actor_id' => $event['actor_id'],
            'actor_type' => $event['actor_type'],
            'old_values' => $event['old_values'],
            'new_values' => $event['new_values'],
            'description' => $event['description'],
            'prior_hash' => $priorHash,
            'created_at' => $event['created_at'],
        ]);

        return hash('sha256', $payload);
    }

    private function buildEvent(int $id, ?string $priorHash, string $createdAt): array
    {
        $event = [
            'id' => $id,
            'event_type' => 'updated',
            'entity_type' => 'Enrollment',
            'entity_id' => 1,
            'actor_id' => 1,
            'actor_type' => 'user',
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'submitted'],
            'description' => 'Test event',
            'prior_hash' => $priorHash,
            'created_at' => $createdAt,
        ];

        $event['event_hash'] = $this->computeEventHash($event, $priorHash);

        return $event;
    }

    public function test_untampered_chain_verifies_cleanly(): void
    {
        $event1 = $this->buildEvent(1, null, '2024-01-01T00:00:00+00:00');
        $event2 = $this->buildEvent(2, $event1['event_hash'], '2024-01-01T00:01:00+00:00');
        $event3 = $this->buildEvent(3, $event2['event_hash'], '2024-01-01T00:02:00+00:00');

        $events = [$event1, $event2, $event3];
        $result = $this->verifyChain($events);

        $this->assertTrue($result['chain_intact']);
        $this->assertEquals(3, $result['valid_links']);
        $this->assertEquals(0, $result['invalid_links']);
    }

    public function test_tampered_event_content_detected(): void
    {
        $event1 = $this->buildEvent(1, null, '2024-01-01T00:00:00+00:00');
        $event2 = $this->buildEvent(2, $event1['event_hash'], '2024-01-01T00:01:00+00:00');

        // Tamper with event2's content but keep its event_hash unchanged
        $event2['new_values'] = ['status' => 'cancelled'];

        $events = [$event1, $event2];
        $result = $this->verifyChain($events);

        $this->assertFalse($result['chain_intact']);
        $this->assertGreaterThan(0, $result['invalid_links']);
    }

    public function test_tampered_prior_hash_detected(): void
    {
        $event1 = $this->buildEvent(1, null, '2024-01-01T00:00:00+00:00');
        $event2 = $this->buildEvent(2, $event1['event_hash'], '2024-01-01T00:01:00+00:00');

        // Break the chain by changing event2's prior_hash
        $event2['prior_hash'] = 'aaaa' . substr($event2['prior_hash'], 4);

        $events = [$event1, $event2];
        $result = $this->verifyChain($events);

        $this->assertFalse($result['chain_intact']);
    }

    public function test_tampered_description_detected(): void
    {
        $event1 = $this->buildEvent(1, null, '2024-01-01T00:00:00+00:00');

        // Tamper description after hash was computed
        $event1['description'] = 'Tampered description';

        $events = [$event1];
        $result = $this->verifyChain($events);

        $this->assertFalse($result['chain_intact']);
        $this->assertEquals(1, $result['invalid_links']);
    }

    /**
     * Simulate the verifyChain logic from AuditService.
     */
    private function verifyChain(array $events): array
    {
        $valid = 0;
        $invalid = 0;
        $errors = [];
        $previousHash = null;

        foreach ($events as $event) {
            if ($event['prior_hash'] !== $previousHash) {
                $invalid++;
                $errors[] = ['event_id' => $event['id'], 'error' => 'prior_hash mismatch'];
            } else {
                $recomputedHash = $this->computeEventHash($event, $event['prior_hash']);
                if ($recomputedHash !== $event['event_hash']) {
                    $invalid++;
                    $errors[] = ['event_id' => $event['id'], 'error' => 'event_hash tampered'];
                } else {
                    $valid++;
                }
            }
            $previousHash = $event['event_hash'];
        }

        return [
            'total_events' => count($events),
            'valid_links' => $valid,
            'invalid_links' => $invalid,
            'chain_intact' => $invalid === 0,
            'errors' => $errors,
        ];
    }
}

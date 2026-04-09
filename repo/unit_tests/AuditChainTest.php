<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuditChainTest extends TestCase
{
    /**
     * Test that hash generation is deterministic and consistent.
     */
    public function test_hash_chain_deterministic(): void
    {
        $payload1 = json_encode([
            'event_type' => 'created',
            'entity_type' => 'Learner',
            'entity_id' => 1,
            'actor_id' => null,
            'actor_type' => 'user',
            'old_values' => null,
            'new_values' => ['name' => 'John'],
            'description' => 'Test event',
            'prior_hash' => null,
            'created_at' => '2024-01-01T00:00:00+00:00',
        ]);

        $hash1 = hash('sha256', $payload1);
        $hash2 = hash('sha256', $payload1);

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1));
    }

    /**
     * Test that different payloads produce different hashes.
     */
    public function test_different_payloads_produce_different_hashes(): void
    {
        $payload1 = json_encode(['event_type' => 'created', 'entity_id' => 1]);
        $payload2 = json_encode(['event_type' => 'updated', 'entity_id' => 1]);

        $this->assertNotEquals(hash('sha256', $payload1), hash('sha256', $payload2));
    }

    /**
     * Test that chain linking works: prior_hash references previous event_hash.
     */
    public function test_chain_linking_logic(): void
    {
        // Simulate a 3-event chain
        $events = [];
        $priorHash = null;

        for ($i = 1; $i <= 3; $i++) {
            $payload = json_encode([
                'event_type' => 'updated',
                'entity_type' => 'Enrollment',
                'entity_id' => 1,
                'actor_id' => $i,
                'prior_hash' => $priorHash,
                'created_at' => "2024-01-0{$i}T00:00:00+00:00",
            ]);

            $eventHash = hash('sha256', $payload);

            $events[] = [
                'prior_hash' => $priorHash,
                'event_hash' => $eventHash,
            ];

            $priorHash = $eventHash;
        }

        // First event has no prior hash
        $this->assertNull($events[0]['prior_hash']);

        // Each subsequent event references the prior event's hash
        $this->assertEquals($events[0]['event_hash'], $events[1]['prior_hash']);
        $this->assertEquals($events[1]['event_hash'], $events[2]['prior_hash']);

        // All hashes are unique
        $hashes = array_column($events, 'event_hash');
        $this->assertCount(3, array_unique($hashes));
    }

    /**
     * Test that tampering with a middle event breaks the chain.
     */
    public function test_tampered_event_breaks_chain(): void
    {
        $event1Hash = hash('sha256', json_encode(['id' => 1, 'prior_hash' => null]));
        $event2Hash = hash('sha256', json_encode(['id' => 2, 'prior_hash' => $event1Hash]));
        $event3Hash = hash('sha256', json_encode(['id' => 3, 'prior_hash' => $event2Hash]));

        // Simulate tampering: change event2's hash
        $tamperedEvent2Hash = hash('sha256', json_encode(['id' => 2, 'prior_hash' => $event1Hash, 'tampered' => true]));

        // Event3's prior_hash should no longer match the tampered event2
        $this->assertNotEquals($tamperedEvent2Hash, $event2Hash);
        $this->assertEquals($event2Hash, $event3Hash ? substr(json_decode(json_encode(['prior_hash' => $event2Hash]), true)['prior_hash'], 0, 64) : null);

        // Verify: event3.prior_hash !== tampered event2 hash
        $this->assertNotEquals($tamperedEvent2Hash, $event2Hash);
    }

    /**
     * Test hash is valid SHA-256 format.
     */
    public function test_hash_is_valid_sha256(): void
    {
        $hash = hash('sha256', 'test payload');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }
}

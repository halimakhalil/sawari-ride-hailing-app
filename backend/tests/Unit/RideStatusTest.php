<?php

namespace Tests\Unit;

use App\Enums\RideStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RideStatusTest extends TestCase
{
    /** @return array<string, array{RideStatus, RideStatus, bool}> */
    public static function transitions(): array
    {
        $allowed = [
            'requested→accepted' => [RideStatus::Requested, RideStatus::Accepted],
            'requested→cancelled' => [RideStatus::Requested, RideStatus::Cancelled],
            'accepted→in_progress' => [RideStatus::Accepted, RideStatus::InProgress],
            'accepted→cancelled' => [RideStatus::Accepted, RideStatus::Cancelled],
            'in_progress→completed' => [RideStatus::InProgress, RideStatus::Completed],
        ];

        $cases = [];
        foreach (RideStatus::cases() as $from) {
            foreach (RideStatus::cases() as $to) {
                $name = "{$from->value}→{$to->value}";
                $cases[$name] = [$from, $to, isset($allowed[$name])];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_transition_matrix(RideStatus $from, RideStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    public function test_terminal_states_are_not_active(): void
    {
        $this->assertFalse(RideStatus::Completed->isActive());
        $this->assertFalse(RideStatus::Cancelled->isActive());
        $this->assertTrue(RideStatus::InProgress->isActive());
    }

    public function test_rider_can_cancel_only_before_the_trip_starts(): void
    {
        $this->assertTrue(RideStatus::Requested->isCancellableByRider());
        $this->assertTrue(RideStatus::Accepted->isCancellableByRider());
        $this->assertFalse(RideStatus::InProgress->isCancellableByRider());
        $this->assertFalse(RideStatus::Completed->isCancellableByRider());
    }
}

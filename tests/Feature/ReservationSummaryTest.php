<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\Hall;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Support\ReservationSummary;

test('a hall booking maps its downpayment column onto paid', function () {
    $hall = Hall::factory()->create(['name' => 'Grand Ballroom', 'rent_price' => 8000, 'skirting_price' => 5000]);

    $booking = Booking::factory()->for($hall)->create([
        'guest_name' => 'Juan dela Cruz',
        'start_date' => '2027-04-18',
        'start_hour' => 8,
        'end_hour' => 12,
        'status' => BookingStatus::Pending,
    ]);

    $summary = ReservationSummary::fromHallBooking($booking);

    expect($summary->type)->toBe('Function hall')
        ->and($summary->reference)->toBe($booking->reference)
        ->and($summary->guestName)->toBe('Juan dela Cruz')
        ->and($summary->detail)->toBe('Grand Ballroom')
        ->and($summary->paid)->toBe($booking->downpayment)
        ->and($summary->total)->toBe($booking->total)
        ->and($summary->balance)->toBe($booking->balance)
        ->and($summary->status)->toBe(BookingStatus::Pending)
        ->and($summary->occursAt->toDateString())->toBe('2027-04-18')
        ->and($summary->occursAtLabel)->toContain('Apr 18, 2027')
        ->and($summary->occursAtLabel)->toContain('8AM–12PM');
});

test('a room booking maps its amount_paid column onto paid', function () {
    $room = Room::factory()->withRates([6 => 1200])->create(['name' => 'Family Room 201']);

    $booking = RoomBooking::factory()->for($room)->create([
        'guest_name' => 'Maria Santos',
        'starts_at' => '2027-05-02 14:00:00',
        'ends_at' => '2027-05-02 20:00:00',
        'hours' => 6,
        'total' => 1200,
        'amount_paid' => 600,
        'balance' => 600,
        'status' => BookingStatus::Confirmed,
    ]);

    $summary = ReservationSummary::fromRoomBooking($booking);

    expect($summary->type)->toBe('Room')
        ->and($summary->detail)->toBe('Family Room 201')
        ->and($summary->paid)->toBe(600)
        ->and($summary->total)->toBe(1200)
        ->and($summary->status)->toBe(BookingStatus::Confirmed)
        ->and($summary->occursAtLabel)->toContain('May 2, 2027')
        ->and($summary->occursAtLabel)->toContain('6 hours');
});

test('a catering order maps its downpayment column onto paid', function () {
    $package = CateringPackage::factory()->create(['name' => 'Mediterranean Mezze', 'price_per_head' => 450, 'minimum_guests' => 1]);

    $order = CateringOrder::factory()->for($package, 'package')->create([
        'guest_name' => 'Ana Reyes',
        'start_date' => '2027-06-11',
        'guests' => 80,
        'status' => BookingStatus::Cancelled,
    ]);

    $summary = ReservationSummary::fromCateringOrder($order);

    expect($summary->type)->toBe('Catering')
        ->and($summary->detail)->toBe('Mediterranean Mezze')
        ->and($summary->paid)->toBe($order->downpayment)
        ->and($summary->status)->toBe(BookingStatus::Cancelled)
        ->and($summary->occursAtLabel)->toContain('Jun 11, 2027')
        ->and($summary->occursAtLabel)->toContain('80 guests');
});

test('all three types produce the same shape', function () {
    $hall = ReservationSummary::fromHallBooking(Booking::factory()->create());
    $room = ReservationSummary::fromRoomBooking(RoomBooking::factory()->create());
    $catering = ReservationSummary::fromCateringOrder(CateringOrder::factory()->create());

    foreach ([$hall, $room, $catering] as $summary) {
        expect($summary->reference)->toBeString()->not->toBeEmpty()
            ->and($summary->guestName)->toBeString()->not->toBeEmpty()
            ->and($summary->detail)->toBeString()->not->toBeEmpty()
            ->and($summary->total)->toBeInt()
            ->and($summary->paid)->toBeInt()
            ->and($summary->balance)->toBeInt();
    }
});

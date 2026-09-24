<?php

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Exceptions\PayMongoException;
use App\Models\Contracts\Reservation;
use App\Support\Availability;
use App\Support\CheckoutRequest;
use App\Support\DateList;
use App\Support\PayMongo;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Base for the guest-facing booking pages, which all reserve a set of chosen days.
 *
 * A base class rather than a trait, for the same reason as ManagesPhotosComponent: the
 * concrete components are anonymous classes inside Blade single-file components, which
 * static analysis cannot see, so a trait would appear unused.
 *
 * Days are taken one at a time rather than as a range. Tapping a day adds it, tapping it
 * again removes it, and the days need not be consecutive — a guest running an event on
 * three separate Saturdays books exactly those three days and pays for three. A range
 * picker could neither express that nor let a guest undo a mis-tap, which is what the
 * resort asked us to fix.
 *
 * Subclasses say which venue's availability to load; everything else — the calendar
 * month, taking days, and counting them — is handled here.
 */
abstract class BooksDatesComponent extends Component
{
    /**
     * The most days one booking may cover.
     *
     * A cap rather than nothing at all: the calendar loads availability for the month on
     * screen, and the submit-time check loads it for everything chosen, so an unbounded
     * selection would eventually query a year at a time.
     */
    public const MAX_DATES = 30;

    /**
     * The days the guest has chosen, as ISO strings, always ascending.
     *
     * @var array<int, string>
     */
    public array $dates = [];

    /**
     * A single day handed over by the homepage availability bar, which is all a shareable
     * link ever carries. Seeded into `$dates` on mount and not used again.
     */
    #[Url(as: 'date')]
    public string $date = '';

    /**
     * The month the calendar is showing, as an ISO date. Empty means "work it out".
     */
    public string $month = '';

    public string $guest_name = '';

    public string $guest_phone = '';

    public string $guest_email = '';

    /**
     * The reference of the reservation this session paid for, which switches the page to
     * the confirmation view. Set from the session flash after PayMongo sends the guest
     * back — never from the query string, which would let anyone read a booking by
     * guessing a six-character reference.
     */
    public ?string $reference = null;

    /**
     * Something went wrong on the way to or back from checkout, worth saying out loud.
     */
    public ?string $paymentError = null;

    /**
     * Which dates are already spoken for, over the window the calendar needs.
     */
    abstract protected function availabilityFor(CarbonInterface $from, CarbonInterface $until): Availability;

    /**
     * Which of the three booking pages this is, as the payment routes name it.
     */
    abstract protected function reservationType(): string;

    /**
     * Rules every booking page shares for the guest's own details.
     *
     * @return array<string, array<int, string>>
     */
    protected function guestRules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'min:2', 'max:100'],
            'guest_phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
            'guest_email' => ['required', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * Clear a field's error the moment the guest fixes it.
     *
     * Livewire keeps validation errors until something clears them, so without this a
     * guest who submitted an incomplete form and then filled the missing field in went on
     * reading "Choose how long you are staying" with a duration plainly selected above it.
     *
     * The whole form is still re-validated on submit; this only takes down a message that
     * has stopped being true.
     */
    public function updated(string $property): void
    {
        $this->resetValidation($property);
    }

    /**
     * Take whatever the session knows: the contact details of a signed-in guest, and the
     * outcome of a checkout they have just come back from.
     */
    protected function prefillFromSession(): void
    {
        if ($user = Auth::user()) {
            $this->guest_name = $user->name;
            $this->guest_email = $user->email;
        }

        $this->reference = session('reservation_reference');
        $this->paymentError = session('payment_error');
    }

    /**
     * Whether the resort can take a payment at all.
     *
     * With no PayMongo key there is nowhere to send the guest, and writing a booking
     * anyway would hold dates against money that was never going to arrive.
     */
    #[Computed]
    public function paymentsAvailable(): bool
    {
        return PayMongo::isConfigured();
    }

    /**
     * Refuse, in plain words, an amount the gateway will not take.
     *
     * Checked here rather than left to the gateway client, and before the reservation is
     * written, for two reasons: a booking must not be created and then deleted again over
     * something knowable up front, and this is not a failure worth reporting — the amount
     * is simply too small, every time, until the price changes.
     *
     * @param  int  $amount  what would be charged now, in whole pesos
     * @param  string|null  $hint  something the guest could do about it, if anything
     */
    protected function assertAmountIsPayable(int $amount, ?string $hint = null): void
    {
        if ($amount >= PayMongo::MINIMUM_PESOS) {
            return;
        }

        $message = __('The ₱:amount due now is below the ₱:minimum our payment provider will accept.', [
            'amount' => number_format($amount),
            'minimum' => number_format(PayMongo::MINIMUM_PESOS),
        ]);

        throw ValidationException::withMessages([
            'dates' => $hint ? $message.' '.$hint : $message.' '.__('Please contact the resort to book this.'),
        ]);
    }

    /**
     * Send the guest off to PayMongo to pay for the reservation just written.
     *
     * The reservation exists before the redirect, and that is what holds its dates while
     * the guest pays — Pending already blocks. The cost is that an abandoned checkout
     * holds dates until `resort:expire-unpaid-reservations` releases them.
     *
     * @param  int  $amount  what to charge now, in whole pesos
     */
    protected function sendToCheckout(Model&Reservation $reservation, int $amount, string $lineItemName): mixed
    {
        $type = $this->reservationType();

        $routeParameters = ['type' => $type, 'reference' => $reservation->reference()];

        try {
            $session = PayMongo::make()->createCheckoutSession(new CheckoutRequest(
                reference: $reservation->reference(),
                description: __(':name — :reference', ['name' => $lineItemName, 'reference' => $reservation->reference()]),
                lineItemName: $lineItemName,
                amount: $amount,
                // Signed, so a stranger cannot cancel a booking by guessing its reference.
                // The url() helper rather than the URL facade: `Url` is already taken here
                // by Livewire's attribute, and the two names differ only by case.
                successUrl: url()->signedRoute('payment.return', $routeParameters),
                cancelUrl: url()->signedRoute('payment.cancel', $routeParameters),
                methods: (array) config('services.paymongo.methods'),
                metadata: $routeParameters,
                guestName: $this->guest_name,
                guestEmail: $this->guest_email,
                guestPhone: $this->guest_phone,
            ));
        } catch (PayMongoException $e) {
            report($e);

            // Nobody has seen this booking and nobody has paid for it, so it must not be
            // left sitting on dates. Deleting it takes its days with it.
            $reservation->delete();

            throw ValidationException::withMessages([
                'dates' => __('We could not reach the payment provider just now. Please try again in a moment.'),
            ]);
        }

        $reservation->recordPayment(PaymentStatus::Awaiting, ['payment_session_id' => $session->id]);

        return $this->redirect($session->checkoutUrl);
    }

    /**
     * The month the calendar is showing — the month of the first chosen day, or this
     * month, until the guest navigates away from it.
     */
    #[Computed]
    public function calendar(): Carbon
    {
        $month = $this->month !== ''
            ? Carbon::parse($this->month)
            : Carbon::parse($this->firstDate() ?? today());

        return $month->startOfMonth();
    }

    /**
     * Which dates the chosen venue is already spoken for.
     *
     * Only the month on screen. Chosen days scattered across the year are checked on
     * submit instead — widening this to cover them would query months of bookings on
     * every tap, for shading the guest cannot see.
     */
    #[Computed]
    public function availability(): Availability
    {
        // Called as a method, not read as `$this->calendar`: Livewire resolves computed
        // properties by magic, which static analysis cannot follow. Blade still reads
        // them as properties, and gets the cached value.
        $month = $this->calendar();

        return $this->availabilityFor(
            $month->copy()->startOfWeek(CarbonInterface::MONDAY),
            $month->copy()->endOfMonth()->endOfWeek(CarbonInterface::MONDAY),
        );
    }

    /**
     * How many days the booking covers. Zero until the guest picks one.
     */
    #[Computed]
    public function days(): int
    {
        return count($this->dates);
    }

    /**
     * How many nights fall inside the booking, for the types that sell them.
     *
     * Only meaningful for an unbroken run; a stay with gaps in it is not a run of nights,
     * which is why the rooms page makes the guest say which they meant.
     */
    #[Computed]
    public function nights(): int
    {
        return max($this->days() - 1, 0);
    }

    /**
     * Whether the guest has chosen any days at all.
     */
    public function hasDates(): bool
    {
        return $this->dates !== [];
    }

    /**
     * The first day chosen, or null when none have been.
     */
    public function firstDate(): ?string
    {
        return $this->dates[0] ?? null;
    }

    /**
     * The last day chosen, or null when none have been.
     */
    public function lastDate(): ?string
    {
        return $this->dates === [] ? null : $this->dates[count($this->dates) - 1];
    }

    /**
     * Whether the chosen days form an unbroken run.
     */
    public function datesAreContiguous(): bool
    {
        return DateList::isContiguous($this->dates);
    }

    /**
     * The days this booking would cover, as ISO strings.
     *
     * Kept apart from `$dates` because the rooms page holds the room for its nights and
     * not for the morning the guest checks out.
     *
     * @return array<int, string>
     */
    public function bookedDates(): array
    {
        return $this->dates;
    }

    /**
     * The chosen days written out, e.g. "September 9, 15 & 20, 2026".
     */
    #[Computed]
    public function datesLabel(): string
    {
        return DateList::label($this->dates);
    }

    /**
     * Take a day from the calendar, or give it back.
     *
     * Tapping a chosen day removes it, which is the whole point: a guest who mis-taps can
     * simply tap again rather than being stuck with a highlighted day they never wanted.
     */
    public function toggleDate(string $date): void
    {
        $picked = rescue(fn () => Carbon::parse($date)->startOfDay(), null, report: false);

        if (! $picked || $picked->lt(today())) {
            return;
        }

        $iso = $picked->toDateString();
        $alreadyChosen = in_array($iso, $this->dates, strict: true);

        if (! $alreadyChosen && count($this->dates) >= self::MAX_DATES) {
            return;
        }

        $this->dates = $alreadyChosen
            ? array_values(array_diff($this->dates, [$iso]))
            : [...$this->dates, $iso];

        // ISO dates sort correctly as strings, so the list stays in calendar order
        // however the guest tapped their way through it.
        sort($this->dates);

        $this->afterDatesPicked();
    }

    /**
     * Give back every chosen day at once.
     */
    public function clearDates(): void
    {
        $this->dates = [];

        $this->afterDatesPicked();
    }

    /**
     * Move the calendar a month at a time, never back past the current month.
     */
    public function shiftMonth(int $delta): void
    {
        $this->month = $this->calendar()
            ->copy()
            ->addMonths($delta)
            ->max(today()->startOfMonth())
            ->toDateString();

        unset($this->calendar, $this->availability);
    }

    /**
     * Clear the chosen days and send the calendar back to where it started.
     */
    protected function resetDates(): void
    {
        $this->dates = [];
        $this->date = '';
        $this->month = '';

        unset($this->calendar, $this->availability, $this->days, $this->nights, $this->datesLabel);
    }

    /**
     * Seed the chosen days from a shared link, ignoring a day the form could never accept
     * so a stale bookmark opens on an empty calendar rather than one the rules reject.
     */
    protected function discardUnusableDate(): void
    {
        if ($this->date === '') {
            return;
        }

        $date = rescue(fn () => Carbon::parse($this->date), null, report: false);

        if (! $date || $date->startOfDay()->lt(today())) {
            $this->date = '';

            return;
        }

        $this->dates = [$date->toDateString()];
    }

    /**
     * Drop the caches the chosen days feed, then let subclasses react.
     */
    protected function afterDatesPicked(): void
    {
        $this->resetValidation('dates');

        // Dropped before the hook runs, so subclasses read the new selection.
        unset($this->days, $this->nights, $this->datesLabel, $this->availability);

        $this->afterDatesChange();
    }

    /**
     * Hook for subclasses that need to recompute something when the days change.
     */
    protected function afterDatesChange(): void
    {
        //
    }
}

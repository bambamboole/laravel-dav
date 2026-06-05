<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use DateTimeInterface;
use DateTimeZone;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property;
use Sabre\VObject\Recur\EventIterator;
use Sabre\VObject\Recur\NoInstancesException;

/**
 * Why: sabre/vobject's VCalendar::expand() hardcodes VEVENT and silently drops
 * recurring VTODO/VJOURNAL. Registered as the VCALENDAR document class so the
 * CalDAV REPORT handlers expand tasks and journals too (RFC 4791 §9.6.5).
 *
 * The body mirrors sabre/vobject 4.6.0; the only divergences are the wider
 * component gate and per-instance DUE shifting for tasks.
 */
class ExpandingVCalendar extends VCalendar
{
    /** @var list<string> */
    private const ExpandableComponents = ['VEVENT', 'VTODO', 'VJOURNAL'];

    public function expand(DateTimeInterface $start, DateTimeInterface $end, ?DateTimeZone $timeZone = null): VCalendar
    {
        $timeZone ??= new DateTimeZone('UTC');

        $stripTimezones = function (Component $component) use ($timeZone, &$stripTimezones): Component {
            foreach ($component->children() as $componentChild) {
                if ($componentChild instanceof Property\ICalendar\DateTime && $componentChild->hasTime()) {
                    $dt = $componentChild->getDateTimes($timeZone);
                    $dt[0] = $dt[0]->setTimeZone(new DateTimeZone('UTC'));
                    $componentChild->setDateTimes($dt);
                } elseif ($componentChild instanceof Component) {
                    $stripTimezones($componentChild);
                }
            }

            return $component;
        };

        $newChildren = [];
        $recurring = [];

        foreach ($this->children() as $child) {
            if ($child instanceof Property && $child->name !== 'PRODID') {
                $newChildren[] = clone $child;

                continue;
            }

            if (! $child instanceof Component || ! in_array($child->name, self::ExpandableComponents, true)) {
                continue;
            }

            if (isset($child->{'RECURRENCE-ID'}) || isset($child->RRULE) || isset($child->RDATE)) {
                $uid = isset($child->UID) ? (string) $child->UID : '';

                if ($uid === '') {
                    throw new InvalidDataException('Every expanded component must have a UID property');
                }

                $recurring[$uid][] = clone $child;
            } elseif ($this->isInTimeRange($child, $start, $end)) {
                $newChildren[] = $stripTimezones(clone $child);
            }
        }

        foreach ($recurring as $components) {
            try {
                $iterator = new EventIterator($components, null, $timeZone);
            } catch (NoInstancesException) {
                continue;
            }

            $overrides = $this->overrideObjectIds($components);
            $dueOffset = $this->masterDueOffset($components, $timeZone);

            $iterator->fastForward($start);

            while ($iterator->valid() && $iterator->getDtStart() < $end) {
                if ($iterator->getDtEnd() > $start) {
                    $instance = $iterator->getEventObject();

                    if ($dueOffset !== null && ! isset($overrides[spl_object_id($instance)])) {
                        $this->shiftDue($instance, $iterator->getDtStart(), $dueOffset);
                    }

                    $newChildren[] = $stripTimezones($instance);
                }

                $iterator->next();
            }
        }

        return new VCalendar($newChildren);
    }

    /**
     * Why: the iterator returns author-provided overrides by reference, so their
     * object ids let us skip DUE shifting and keep their own DTSTART/DUE.
     *
     * @param  list<Component>  $components
     * @return array<int, true>
     */
    private function overrideObjectIds(array $components): array
    {
        $ids = [];

        foreach ($components as $component) {
            if (isset($component->{'RECURRENCE-ID'})) {
                $ids[spl_object_id($component)] = true;
            }
        }

        return $ids;
    }

    /**
     * Why: each generated VTODO instance keeps the master's DTSTART→DUE gap.
     * Null when there is nothing to shift (no master, not a task, or no DUE).
     *
     * @param  list<Component>  $components
     */
    private function masterDueOffset(array $components, DateTimeZone $timeZone): ?int
    {
        foreach ($components as $component) {
            if (isset($component->{'RECURRENCE-ID'})) {
                continue;
            }

            if ($component->name !== 'VTODO' || ! isset($component->DTSTART, $component->DUE)) {
                return null;
            }

            return $component->DUE->getDateTime($timeZone)->getTimestamp()
                - $component->DTSTART->getDateTime($timeZone)->getTimestamp();
        }

        return null;
    }

    private function isInTimeRange(Component $component, DateTimeInterface $start, DateTimeInterface $end): bool
    {
        if (! method_exists($component, 'isInTimeRange')) {
            return false;
        }

        return $component->isInTimeRange($start, $end);
    }

    private function shiftDue(Component $instance, DateTimeInterface $instanceStart, int $offsetSeconds): void
    {
        if (! isset($instance->DUE)) {
            return;
        }

        $due = (new \DateTimeImmutable('@'.($instanceStart->getTimestamp() + $offsetSeconds)))
            ->setTimezone($instanceStart->getTimezone());

        $instance->DUE->setDateTime($due, $instance->DUE->isFloating());
    }
}

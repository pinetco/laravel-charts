<?php

namespace Pinetco\Charts;

use Carbon\Carbon;

abstract class Chart
{
    /** @var Carbon */
    protected $from;

    /** @var Carbon */
    protected $till;

    protected $period = self::PERIOD_DAILY;

    protected $cumulative = false;

    protected $filters = [];

    protected $categories = [];

    protected $series = [];

    protected $dailyFormat;
    protected $weeklyFormat;
    protected $monthlyFormat;

    const PERIOD_DAILY = 'daily';
    const PERIOD_WEEKLY = 'weekly';
    const PERIOD_MONTHLY = 'monthly';

    public function __construct(Carbon $from, Carbon $till)
    {
        $this->from = $from;
        $this->till = $till;

        $this->dailyFormat = $this->dailyFormat ?: config('charts.formats.daily');
        $this->weeklyFormat = $this->weeklyFormat ?: config('charts.formats.weekly');
        $this->monthlyFormat = $this->monthlyFormat ?: config('charts.formats.monthly');
    }

    public function period(string $period)
    {
        if (in_array($period, [self::PERIOD_DAILY, self::PERIOD_WEEKLY, self::PERIOD_MONTHLY])) {
            $this->period = $period;
        }

        return $this;
    }

    public function cumulative(bool $boolean)
    {
        $this->cumulative = $boolean;

        return $this;
    }

    public function filters(array $filters)
    {
        $this->filters = $filters;

        return $this;
    }

    public function hasFilters()
    {
        return count($this->filters) > 0;
    }

    public function get()
    {
        if (method_exists($this, $this->period)) {
            $this->{$this->period}();
        }

        return [
            'categories' => $this->getCategories(),
            'series'     => $this->series,
        ];
    }

    public function addSeries($name, $data, $color = null, $stack = null, $type = null)
    {
        $array = compact('name', 'color', 'stack', 'type');

        $array['data'] = collect($data)->transform(function ($value) {
            return round($value, 2);
        });

        return array_push($this->series, array_filter($array));
    }

    public function addValue($collection, Carbon $date, $value)
    {
        switch ($this->period) {
            case self::PERIOD_DAILY:
                $collection[$date->format($this->dailyFormat)] += $value;
                break;

            case self::PERIOD_WEEKLY:
                $collection[$date->format($this->weeklyFormat)]['value'] += $value;
                break;

            case self::PERIOD_MONTHLY:
                $collection[$date->format($this->monthlyFormat)]['value'] += $value;
                break;
        }
    }

    public function getCategories()
    {
        switch ($this->period) {
            case self::PERIOD_DAILY:
                return $this->datesDaily()->keys();

            case self::PERIOD_WEEKLY:
                return $this->datesWeekly()->transform(function ($date) {
                    return $date['from']->format('d.m.Y') . ' - ' . $date['to']->format('d.m.Y');
                })->values();

            case self::PERIOD_MONTHLY:
                return $this->datesMonthly()->transform(function ($date) {
                    return $date['from']->format('M-y');
                })->values();
        }
    }

    public function cumulative_values($values, $startingAmount = 0)
    {
        foreach ($values as $key => $amount) {
            if ($key == 0) {
                $values[$key] = $startingAmount ? $startingAmount + $amount : $amount;
            } else {
                $values[$key] = $values[$key - 1] + $amount;
            }
        }

        return $values;
    }

    public function cumulative_series($values)
    {
        foreach ($values as $key => $amount) {
            if ($key == 0) {
                $values[$key] = $amount;
            } else {
                $values[$key] = $amount ?: $values[$key - 1] + $amount;
            }
        }

        return $values;
    }

    public function datesDaily()
    {
        $dates = collect();

        $from = $this->from->copy();
        $till = $this->till->copy();

        while ($from->lte($till)) {
            $dates->put($from->format($this->dailyFormat), 0);

            $from->addDay();
        }

        return $dates;
    }

    public function datesWeekly()
    {
        $dates = collect();

        $from = $this->from->copy();
        $till = $this->till->copy();

        while ($from->lte($till)) {
            $dates->put($from->format($this->weeklyFormat), collect([
                'from'  => $from->copy()->startOfWeek(),
                'to'    => $from->copy()->endOfWeek(),
                'value' => 0,
            ]));

            $from->addWeek();
        }

        return $dates;
    }

    public function datesMonthly()
    {
        $dates = collect();

        $from = $this->from->copy()->startOfMonth();
        $till = $this->till->copy()->endOfMonth();

        while ($from->lte($till)) {
            $dates->put($from->format($this->monthlyFormat), collect([
                'from'  => $from->copy()->startOfMonth(),
                'to'    => $from->copy()->endOfMonth(),
                'value' => 0,
            ]));

            $from->addMonthsNoOverflow(1)->startOfMonth();
        }

        return $dates;
    }
}
